<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Item;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creation, deletion and read models for the project tree.
 *
 * The tree alternates project -> item -> project: a board holds cards, and a
 * card of type "subproject" opens into another project, which is itself either
 * a board or a log. Nesting is unbounded.
 */
class ProjectTree
{
    public function createBoard(User $user, ?Item $parentItem, string $name, ?string $description = null): Project
    {
        return $this->createProject($user, $parentItem, $name, Project::KIND_BOARD, $description);
    }

    public function createLog(User $user, ?Item $parentItem, string $name, ?string $description = null): Project
    {
        return $this->createProject($user, $parentItem, $name, Project::KIND_LOG, $description);
    }

    public function createProject(
        User $user,
        ?Item $parentItem,
        string $name,
        string $kind,
        ?string $description = null,
    ): Project {
        return DB::transaction(function () use ($user, $parentItem, $name, $kind, $description) {
            $project = Project::create([
                'user_id' => $user->id,
                'parent_item_id' => $parentItem?->id,
                'name' => $name,
                'description' => $description,
                'kind' => $kind,
            ]);

            if ($project->isBoard()) {
                $this->seedDefaultColumns($project);
            }

            return $project;
        });
    }

    public function seedDefaultColumns(Project $project): void
    {
        foreach (Project::DEFAULT_COLUMNS as $position => $name) {
            BoardColumn::create([
                'project_id' => $project->id,
                'name' => $name,
                'position' => $position,
            ]);
        }
    }

    /**
     * Delete a project and everything below it.
     *
     * Columns, items and log entries go with the project via foreign keys; the
     * project -> item edge is not a constrained cascade (it would form a cycle
     * MySQL cannot resolve), so nested sub-projects are walked by hand.
     */
    public function deleteProject(Project $project): void
    {
        DB::transaction(function () use ($project) {
            $childProjects = Project::whereIn(
                'parent_item_id',
                Item::where('project_id', $project->id)->select('id')
            )->get();

            foreach ($childProjects as $child) {
                $this->deleteProject($child);
            }

            $project->delete();
        });
    }

    /**
     * Delete a card, plus the sub-project it opens into, if any.
     */
    public function deleteItem(Item $item): void
    {
        DB::transaction(function () use ($item) {
            $child = Project::where('parent_item_id', $item->id)->first();

            if ($child) {
                $this->deleteProject($child);
            }

            $item->delete();
        });
    }

    /**
     * Turn a plain card into a sub-project card by giving it a child project.
     */
    public function attachSubproject(Item $item, string $kind, ?string $name = null): Project
    {
        return DB::transaction(function () use ($item, $kind, $name) {
            $existing = Project::where('parent_item_id', $item->id)->first();

            if ($existing) {
                return $existing;
            }

            $item->update(['type' => Item::TYPE_SUBPROJECT]);

            return $this->createProject(
                $item->project->user,
                $item,
                $name ?: $item->title,
                $kind,
            );
        });
    }

    /**
     * The whole tree for the sidebar, as nested plain arrays.
     *
     * @return list<array<string, mixed>>
     */
    public function treeFor(User $user): array
    {
        $projects = Project::where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'name', 'kind', 'parent_item_id']);

        $items = Item::whereIn('project_id', $projects->pluck('id'))
            ->where('type', Item::TYPE_SUBPROJECT)
            ->get(['id', 'project_id', 'title', 'position', 'board_column_id']);

        $itemToParentProject = $items->pluck('project_id', 'id');

        /** @var array<int, list<int>> $childrenOf */
        $childrenOf = [];
        /** @var list<int> $roots */
        $roots = [];

        $nodes = $projects->mapWithKeys(fn (Project $project) => [
            $project->id => [
                'id' => $project->id,
                'name' => $project->name,
                'kind' => $project->kind,
                'children' => [],
            ],
        ])->all();

        foreach ($projects as $project) {
            $parentProjectId = $project->parent_item_id
                ? ($itemToParentProject[$project->parent_item_id] ?? null)
                : null;

            if ($parentProjectId === null) {
                $roots[] = $project->id;

                continue;
            }

            $childrenOf[$parentProjectId][] = $project->id;
        }

        $build = function (int $id) use (&$build, $nodes, $childrenOf): array {
            $node = $nodes[$id];
            $node['children'] = array_map($build, $childrenOf[$id] ?? []);

            return $node;
        };

        return array_map($build, $roots);
    }

    /**
     * Flat list of the user's boards, labelled with their path, for pickers.
     *
     * @return list<array{id: int, label: string}>
     */
    public function boardOptions(User $user): array
    {
        $options = [];

        $walk = function (array $nodes, string $prefix) use (&$walk, &$options): void {
            foreach ($nodes as $node) {
                $label = $prefix === '' ? $node['name'] : $prefix.' / '.$node['name'];

                if ($node['kind'] === Project::KIND_BOARD) {
                    $options[] = ['id' => $node['id'], 'label' => $label];
                }

                $walk($node['children'], $label);
            }
        };

        $walk($this->treeFor($user), '');

        return $options;
    }
}
