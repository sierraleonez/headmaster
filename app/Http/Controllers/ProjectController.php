<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Item;
use App\Models\LogEntry;
use App\Models\Project;
use App\Services\ProjectTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectTree $tree) {}

    /**
     * Send the user to their root board.
     */
    public function home(Request $request): RedirectResponse
    {
        return redirect()->route('projects.show', $request->user()->rootProject());
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        return Inertia::render('project', [
            'project' => $this->payload($project),
            'trail' => $this->trail($project),
        ]);
    }

    /**
     * Create a sub-project hanging off a card.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'item_id' => ['required', 'integer', 'exists:items,id'],
            'kind' => ['required', Rule::in([Project::KIND_BOARD, Project::KIND_LOG])],
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $item = Item::findOrFail($request->integer('item_id'));
        $this->authorize('update', $item);

        $project = $this->tree->attachSubproject(
            $item,
            $validated['kind'],
            $validated['name'] ?? null,
        );

        return redirect()->route('projects.show', $project);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $project->update($validated);

        // The card that opens into this project mirrors its name.
        if (isset($validated['name']) && $project->parent_item_id) {
            Item::where('id', $project->parent_item_id)->update(['title' => $validated['name']]);
        }

        return back();
    }

    public function destroy(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $parentItem = $project->parentItem;
        $parentProject = $parentItem->project ?? $request->user()->rootProject();

        $this->tree->deleteProject($project);

        if ($parentItem) {
            $this->tree->deleteItem($parentItem);
        }

        return redirect()->route('projects.show', $parentProject);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Project $project): array
    {
        $base = [
            'id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'kind' => $project->kind,
            'is_root' => $project->isRoot(),
        ];

        if ($project->isBoard()) {
            $project->load(['columns.items' => fn ($query) => $query->with('child:id,parent_item_id,kind,name')]);

            $base['columns'] = $project->columns
                ->map(fn (BoardColumn $column) => $this->columnPayload($column))
                ->values();

            return $base;
        }

        $base['entries'] = $project->logEntries
            ->map(fn (LogEntry $entry) => [
                'id' => $entry->id,
                'logged_on' => $entry->logged_on->toDateString(),
                'title' => $entry->title,
                'body' => $entry->body,
            ])
            ->values();

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function columnPayload(BoardColumn $column): array
    {
        return [
            'id' => $column->id,
            'name' => $column->name,
            'position' => $column->position,
            'items' => $column->items
                ->map(fn (Item $item) => $this->itemPayload($item))
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function itemPayload(Item $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'body' => $item->body,
            'type' => $item->type,
            'position' => $item->position,
            'board_column_id' => $item->board_column_id,
            'child' => $item->child ? [
                'id' => $item->child->id,
                'kind' => $item->child->kind,
            ] : null,
        ];
    }

    /**
     * Breadcrumb trail from the root board down to this project.
     *
     * @return list<array<string, mixed>>
     */
    private function trail(Project $project): array
    {
        return array_map(fn (Project $ancestor) => [
            'id' => $ancestor->id,
            'name' => $ancestor->name,
            'kind' => $ancestor->kind,
        ], $project->ancestors());
    }
}
