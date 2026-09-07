<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Item;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $board;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->board = $this->user->rootProject();
    }

    public function test_columns_can_be_added_renamed_reordered_and_removed(): void
    {
        $this->actingAs($this->user)
            ->post(route('columns.store', $this->board), ['name' => 'Waiting'])
            ->assertRedirect();

        $added = BoardColumn::where('name', 'Waiting')->firstOrFail();
        $this->assertSame(6, $added->position, 'new columns land at the end');

        $this->actingAs($this->user)
            ->patch(route('columns.update', $added), ['name' => 'On hold'])
            ->assertRedirect();

        $this->assertSame('On hold', $added->fresh()->name);

        $reversed = $this->board->columns()->pluck('id')->reverse()->values()->all();

        $this->actingAs($this->user)
            ->post(route('columns.reorder', $this->board), ['ids' => $reversed])
            ->assertRedirect();

        $this->assertSame($reversed, $this->board->fresh()->columns()->pluck('id')->all());

        $this->actingAs($this->user)
            ->delete(route('columns.destroy', $added))
            ->assertRedirect();

        $this->assertNull(BoardColumn::find($added->id));
    }

    public function test_deleting_a_column_removes_its_cards_and_their_sub_projects(): void
    {
        $column = $this->board->columns()->first();

        $card = Item::create([
            'project_id' => $this->board->id,
            'board_column_id' => $column->id,
            'title' => 'Phase 1',
        ]);
        $child = app(ProjectTree::class)->attachSubproject($card, Project::KIND_BOARD);

        $this->actingAs($this->user)
            ->delete(route('columns.destroy', $column))
            ->assertRedirect();

        $this->assertNull(Item::find($card->id));
        $this->assertNull(Project::find($child->id));
    }

    public function test_a_card_can_be_created_edited_and_deleted(): void
    {
        $column = $this->board->columns()->first();

        $this->actingAs($this->user)
            ->post(route('items.store', $column), ['title' => 'Read chapter 1'])
            ->assertRedirect();

        $card = Item::firstOrFail();
        $this->assertSame(Item::TYPE_NOTE, $card->type);
        $this->assertSame($this->board->id, $card->project_id);

        $this->actingAs($this->user)
            ->patch(route('items.update', $card), [
                'title' => 'Read chapter 2',
                'body' => "## Notes\n\n- ownership\n- borrowing",
            ])
            ->assertRedirect();

        $card->refresh();
        $this->assertSame('Read chapter 2', $card->title);
        $this->assertStringContainsString('borrowing', $card->body);

        $this->actingAs($this->user)
            ->delete(route('items.destroy', $card))
            ->assertRedirect();

        $this->assertNull(Item::find($card->id));
    }

    public function test_renaming_a_sub_project_card_renames_the_project_behind_it(): void
    {
        $card = Item::create([
            'project_id' => $this->board->id,
            'board_column_id' => $this->board->columns()->first()->id,
            'title' => 'Old name',
        ]);
        $child = app(ProjectTree::class)->attachSubproject($card, Project::KIND_BOARD);

        $this->actingAs($this->user)
            ->patch(route('items.update', $card), ['title' => 'New name'])
            ->assertRedirect();

        $this->assertSame('New name', $child->fresh()->name);

        // ...and the other way around.
        $this->actingAs($this->user)
            ->patch(route('projects.update', $child), ['name' => 'Newer name'])
            ->assertRedirect();

        $this->assertSame('Newer name', $card->fresh()->title);
    }

    public function test_a_card_moves_to_any_column_at_any_position(): void
    {
        [$backlog, $todo] = $this->board->columns->take(2)->all();

        $cards = collect(['a', 'b', 'c'])->map(fn ($title, $index) => Item::create([
            'project_id' => $this->board->id,
            'board_column_id' => $backlog->id,
            'title' => $title,
            'position' => $index,
        ]));

        // Third card to the front of another column.
        $this->actingAs($this->user)
            ->post(route('items.move', $cards[2]), [
                'board_column_id' => $todo->id,
                'position' => 0,
            ])
            ->assertRedirect();

        $this->assertSame([$cards[2]->id], $todo->items()->pluck('id')->all());
        $this->assertSame(
            [$cards[0]->id, $cards[1]->id],
            $backlog->items()->pluck('id')->all(),
        );
        $this->assertSame([0, 1], $backlog->items()->pluck('position')->all());

        // ...and back, into the middle.
        $this->actingAs($this->user)
            ->post(route('items.move', $cards[2]), [
                'board_column_id' => $backlog->id,
                'position' => 1,
            ])
            ->assertRedirect();

        $this->assertSame(
            [$cards[0]->id, $cards[2]->id, $cards[1]->id],
            $backlog->items()->pluck('id')->all(),
        );
    }

    public function test_a_card_cannot_be_moved_onto_someone_elses_board(): void
    {
        $card = Item::create([
            'project_id' => $this->board->id,
            'board_column_id' => $this->board->columns()->first()->id,
            'title' => 'Mine',
        ]);

        $stranger = User::factory()->create();

        $this->actingAs($this->user)
            ->post(route('items.move', $card), [
                'board_column_id' => $stranger->rootProject()->columns()->first()->id,
                'position' => 0,
            ])
            ->assertForbidden();
    }
}
