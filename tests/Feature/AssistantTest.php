<?php

namespace Tests\Feature;

use App\Jobs\DraftPlan;
use App\Models\ChatMessage;
use App\Models\Item;
use App\Models\LogEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\PlanApplier;
use App\Services\PlanAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A plan shaped the way the assistant is asked to return them.
     *
     * @return array<string, mixed>
     */
    private function plan(): array
    {
        return [
            'name' => 'Learn Rust',
            'kind' => 'board',
            'description' => 'Six weeks of the book.',
            'columns' => ['Backlog', 'Todo', 'Done'],
            'items' => [
                [
                    'title' => 'Chapter 1',
                    'body' => 'Install the toolchain.',
                    'column' => 'Todo',
                    'type' => 'note',
                ],
                [
                    'title' => 'Ownership',
                    'column' => 'Backlog',
                    'type' => 'subproject',
                    'project' => [
                        'name' => 'Ownership',
                        'kind' => 'log',
                        'entries' => [
                            ['logged_on' => '2026-09-01', 'title' => 'Read 4.1'],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_a_plan_becomes_a_sub_project_on_the_chosen_board(): void
    {
        $user = User::factory()->create();
        $board = $user->rootProject();

        $project = app(PlanApplier::class)->apply($user, $board, $this->plan());

        // The card that opens it sits on the target board.
        $card = Item::where('project_id', $board->id)->firstOrFail();
        $this->assertSame('Learn Rust', $card->title);
        $this->assertSame(Item::TYPE_SUBPROJECT, $card->type);
        $this->assertSame('Backlog', $card->column->name, 'lands in the first column');

        // The plan's own columns replace the defaults.
        $this->assertSame(
            ['Backlog', 'Todo', 'Done'],
            $project->columns()->pluck('name')->all(),
        );

        $chapter = Item::where('project_id', $project->id)->where('title', 'Chapter 1')->firstOrFail();
        $this->assertSame('Todo', $chapter->column->name);

        // ...and the nested log came with its entry.
        $ownership = Item::where('project_id', $project->id)->where('title', 'Ownership')->firstOrFail();
        $log = Project::where('parent_item_id', $ownership->id)->firstOrFail();

        $this->assertSame(Project::KIND_LOG, $log->kind);
        $this->assertSame('Read 4.1', LogEntry::where('project_id', $log->id)->firstOrFail()->title);
    }

    public function test_a_malformed_plan_is_reduced_to_something_safe(): void
    {
        $normalised = app(PlanApplier::class)->normalise([
            'name' => '   ',
            'kind' => 'nonsense',
            'columns' => ['Todo', 42, ''],
            'items' => [
                'not an array',
                ['title' => 'Orphan subproject', 'type' => 'subproject'],
            ],
        ]);

        $this->assertSame('Untitled', $normalised['name']);
        $this->assertSame(Project::KIND_BOARD, $normalised['kind']);
        $this->assertSame(['Todo'], $normalised['columns']);
        $this->assertCount(1, $normalised['items']);
        // A sub-project without a project behind it falls back to a note.
        $this->assertSame(Item::TYPE_NOTE, $normalised['items'][0]['type']);
        $this->assertSame('Backlog', $normalised['items'][0]['column']);
    }

    public function test_the_reply_is_drafted_off_the_request(): void
    {
        config(['services.openrouter.key' => 'test-key']);

        Http::fake();
        Queue::fake();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chat.store'), ['content' => 'Map my plan.'])
            ->assertRedirect();

        // The model is never called while the browser is waiting: PHP's
        // max_execution_time kills the request long before a slow reply lands.
        Http::assertNothingSent();
        Queue::assertPushed(DraftPlan::class);

        $reply = ChatMessage::where('role', 'assistant')->firstOrFail();
        $this->assertSame(ChatMessage::STATUS_PENDING, $reply->status);
        $this->assertNull($reply->content);
    }

    public function test_the_queued_job_fills_the_pending_reply_in(): void
    {
        config(['services.openrouter.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'message' => 'Here is a draft.',
                            'plan' => $this->plan(),
                        ]),
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chat.store'), [
                'content' => 'Map my six week Rust plan.',
                'target_project_id' => $user->rootProject()->id,
            ])
            ->assertRedirect();

        $reply = ChatMessage::where('role', 'assistant')->firstOrFail();

        $this->assertSame(ChatMessage::STATUS_READY, $reply->status);
        $this->assertSame('Here is a draft.', $reply->content);
        $this->assertSame('Learn Rust', $reply->plan['name']);
        $this->assertNull($reply->applied_project_id);
        $this->assertSame(0, Item::count(), 'nothing is created until the draft is accepted');
    }

    public function test_the_edited_draft_is_what_gets_created(): void
    {
        $user = User::factory()->create();
        $board = $user->rootProject();

        $conversation = $user->conversations()->create(['title' => 'Rust']);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => 'Here is a draft.',
            'plan' => app(PlanApplier::class)->normalise($this->plan()),
        ]);

        $edited = $this->plan();
        $edited['name'] = 'Learn Rust, properly';
        $edited['items'] = [$edited['items'][0]];

        $this->actingAs($user)
            ->post(route('chat.apply', $message), [
                'project_id' => $board->id,
                'plan' => $edited,
            ])
            ->assertRedirect();

        $created = Project::where('name', 'Learn Rust, properly')->firstOrFail();

        $this->assertSame(1, Item::where('project_id', $created->id)->count());
        $this->assertSame($created->id, $message->fresh()->applied_project_id);
        $this->assertSame('Learn Rust, properly', $message->fresh()->plan['name']);
    }

    public function test_the_page_reports_the_status_the_client_polls_for(): void
    {
        config(['services.openrouter.key' => 'test-key']);

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'message' => 'Here is a draft.',
                    'plan' => $this->plan(),
                ])]]],
            ]),
        ]);

        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Rust']);
        $pending = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => ChatMessage::STATUS_PENDING,
        ]);

        $this->actingAs($user)
            ->get(route('chat.show', $conversation))
            ->assertInertia(fn ($page) => $page
                ->component('chat')
                ->where('conversation.messages.0.status', ChatMessage::STATUS_PENDING)
                ->where('conversation.messages.0.plan', null)
            );

        (new DraftPlan($pending->id))->handle(
            app(PlanAssistant::class),
            app(PlanApplier::class),
        );

        // The next poll picks the finished draft up.
        $this->actingAs($user)
            ->get(route('chat.show', $conversation))
            ->assertInertia(fn ($page) => $page
                ->where('conversation.messages.0.status', ChatMessage::STATUS_READY)
                ->where('conversation.messages.0.plan.name', 'Learn Rust')
            );
    }

    public function test_a_missing_api_key_is_reported_on_the_message(): void
    {
        config(['services.openrouter.key' => null]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('chat.store'), ['content' => 'Hello?'])
            ->assertRedirect();

        $reply = ChatMessage::where('role', 'assistant')->firstOrFail();

        $this->assertSame(ChatMessage::STATUS_FAILED, $reply->status);
        $this->assertStringContainsString('OPENROUTER_API_KEY', (string) $reply->content);
    }

    public function test_a_failed_attempt_is_not_fed_back_to_the_model(): void
    {
        config(['services.openrouter.key' => 'test-key']);

        $user = User::factory()->create();
        $conversation = $user->conversations()->create(['title' => 'Rust']);

        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Map my plan.',
        ]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'status' => ChatMessage::STATUS_FAILED,
            'content' => 'The assistant could not be reached: 502',
        ]);
        $conversation->messages()->create([
            'role' => 'user',
            'content' => 'Try again.',
        ]);
        $pending = $conversation->messages()->create([
            'role' => 'assistant',
            'status' => ChatMessage::STATUS_PENDING,
        ]);

        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode([
                    'message' => 'Here is a draft.',
                    'plan' => $this->plan(),
                ])]]],
            ]),
        ]);

        (new DraftPlan($pending->id))->handle(
            app(PlanAssistant::class),
            app(PlanApplier::class),
        );

        Http::assertSent(function ($request) {
            $roles = collect($request['messages'])->pluck('role')->all();

            // system, user, user - the failed attempt carried no answer.
            $this->assertSame(['system', 'user', 'user'], $roles);

            return true;
        });

        $this->assertSame(ChatMessage::STATUS_READY, $pending->fresh()->status);
    }

    public function test_a_plan_cannot_be_applied_to_someone_elses_board(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $conversation = $user->conversations()->create(['title' => 'Rust']);
        $message = $conversation->messages()->create([
            'role' => 'assistant',
            'plan' => app(PlanApplier::class)->normalise($this->plan()),
        ]);

        $this->actingAs($user)
            ->post(route('chat.apply', $message), [
                'project_id' => $stranger->rootProject()->id,
                'plan' => $this->plan(),
            ])
            ->assertForbidden();
    }
}
