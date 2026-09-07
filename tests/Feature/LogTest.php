<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\LogEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $log;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $board = $this->user->rootProject();

        $card = Item::create([
            'project_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'title' => 'Workout log',
        ]);

        $this->log = app(ProjectTree::class)
            ->attachSubproject($card, Project::KIND_LOG, 'Workout log');
    }

    public function test_entries_can_be_added_edited_and_removed(): void
    {
        $this->actingAs($this->user)
            ->post(route('entries.store', $this->log), [
                'logged_on' => '2026-09-07',
                'title' => 'Push day',
                'body' => "Bench 5x5 @ 80kg\n\nFelt heavy.",
            ])
            ->assertRedirect();

        $entry = LogEntry::firstOrFail();
        $this->assertSame('2026-09-07', $entry->logged_on->toDateString());

        $this->actingAs($this->user)
            ->patch(route('entries.update', $entry), ['title' => 'Push day A'])
            ->assertRedirect();

        $this->assertSame('Push day A', $entry->fresh()->title);

        $this->actingAs($this->user)
            ->delete(route('entries.destroy', $entry))
            ->assertRedirect();

        $this->assertNull(LogEntry::find($entry->id));
    }

    public function test_a_log_renders_its_entries_newest_first(): void
    {
        foreach (['2026-01-01', '2026-03-01', '2026-02-01'] as $date) {
            LogEntry::create([
                'project_id' => $this->log->id,
                'logged_on' => $date,
                'title' => $date,
            ]);
        }

        $this->actingAs($this->user)
            ->get(route('projects.show', $this->log))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('project')
                ->where('project.kind', Project::KIND_LOG)
                ->where('project.entries.0.title', '2026-03-01')
                ->where('project.entries.2.title', '2026-01-01')
            );
    }

    public function test_entries_belong_to_their_owner(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->post(route('entries.store', $this->log), [
                'logged_on' => '2026-09-07',
                'title' => 'Not mine',
            ])
            ->assertForbidden();
    }
}
