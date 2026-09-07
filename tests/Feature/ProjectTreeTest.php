<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_user_gets_a_root_board_with_the_default_columns(): void
    {
        $user = User::factory()->create();

        $root = $user->projects()->whereNull('parent_item_id')->firstOrFail();

        $this->assertSame(Project::KIND_BOARD, $root->kind);
        $this->assertSame(
            Project::DEFAULT_COLUMNS,
            $root->columns->pluck('name')->all(),
        );
    }

    public function test_the_dashboard_sends_the_user_to_their_root_board(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('projects.show', $user->rootProject()));
    }

    public function test_a_card_can_become_a_sub_project_and_nest_without_limit(): void
    {
        $user = User::factory()->create();
        $board = $user->rootProject();
        $column = $board->columns()->first();

        $this->actingAs($user)
            ->post(route('items.store', $column), ['title' => 'Learn Rust'])
            ->assertRedirect();

        $card = Item::firstOrFail();

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'item_id' => $card->id,
                'kind' => Project::KIND_BOARD,
            ])
            ->assertRedirect();

        $child = Project::where('parent_item_id', $card->id)->firstOrFail();
        $this->assertSame(Item::TYPE_SUBPROJECT, $card->fresh()->type);
        $this->assertCount(6, $child->columns);

        // ...and again, one level deeper, this time as a log.
        $grandCard = Item::create([
            'project_id' => $child->id,
            'board_column_id' => $child->columns()->first()->id,
            'title' => 'Practice log',
        ]);

        $this->actingAs($user)
            ->post(route('projects.store'), [
                'item_id' => $grandCard->id,
                'kind' => Project::KIND_LOG,
            ])
            ->assertRedirect();

        $grandChild = Project::where('parent_item_id', $grandCard->id)->firstOrFail();

        $this->assertSame(Project::KIND_LOG, $grandChild->kind);
        $this->assertCount(0, $grandChild->columns);
        $this->assertSame(
            [$board->id, $child->id, $grandChild->id],
            collect($grandChild->ancestors())->pluck('id')->all(),
        );
    }

    public function test_deleting_a_project_takes_the_whole_subtree_with_it(): void
    {
        $user = User::factory()->create();
        $tree = app(ProjectTree::class);
        $board = $user->rootProject();

        $card = Item::create([
            'project_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'title' => 'Phase 1',
        ]);
        $child = $tree->attachSubproject($card, Project::KIND_BOARD);

        $innerCard = Item::create([
            'project_id' => $child->id,
            'board_column_id' => $child->columns()->first()->id,
            'title' => 'Week 1',
        ]);
        $grandChild = $tree->attachSubproject($innerCard, Project::KIND_LOG);

        $this->actingAs($user)
            ->delete(route('projects.destroy', $child))
            ->assertRedirect(route('projects.show', $board));

        $this->assertNull(Project::find($child->id));
        $this->assertNull(Project::find($grandChild->id));
        $this->assertNull(Item::find($card->id), 'the card that opened the project goes too');
        $this->assertNull(Item::find($innerCard->id));
        $this->assertNotNull(Project::find($board->id));
    }

    public function test_the_root_board_cannot_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('projects.destroy', $user->rootProject()))
            ->assertForbidden();
    }

    public function test_one_user_cannot_see_another_users_board(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        $this->actingAs($mine)
            ->get(route('projects.show', $theirs->rootProject()))
            ->assertForbidden();
    }

    public function test_the_sidebar_tree_nests_projects_the_way_they_are_built(): void
    {
        $user = User::factory()->create();
        $tree = app(ProjectTree::class);
        $board = $user->rootProject();

        $card = Item::create([
            'project_id' => $board->id,
            'board_column_id' => $board->columns()->first()->id,
            'title' => 'Research',
        ]);
        $tree->attachSubproject($card, Project::KIND_BOARD, 'Research');

        $nodes = $tree->treeFor($user);

        $this->assertCount(1, $nodes);
        $this->assertSame('HeadMaster', $nodes[0]['name']);
        $this->assertCount(1, $nodes[0]['children']);
        $this->assertSame('Research', $nodes[0]['children'][0]['name']);
    }
}
