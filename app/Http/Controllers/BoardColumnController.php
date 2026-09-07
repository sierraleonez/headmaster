<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Project;
use App\Services\ProjectTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BoardColumnController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        BoardColumn::create([
            'project_id' => $project->id,
            'name' => $validated['name'],
            'position' => (int) $project->columns()->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, BoardColumn $column): RedirectResponse
    {
        $this->authorize('update', $column);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $column->update($validated);

        return back();
    }

    /**
     * Deleting a column deletes its cards, so the client confirms first.
     */
    public function destroy(BoardColumn $column): RedirectResponse
    {
        $this->authorize('delete', $column);

        $tree = app(ProjectTree::class);

        foreach ($column->items as $item) {
            $tree->deleteItem($item);
        }

        $column->delete();

        return back();
    }

    /**
     * Persist a drag-and-drop reordering of the whole column strip.
     */
    public function reorder(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        $owned = $project->columns()->pluck('id')->all();

        DB::transaction(function () use ($validated, $owned) {
            foreach ($validated['ids'] as $position => $id) {
                if (in_array((int) $id, $owned, true)) {
                    BoardColumn::where('id', $id)->update(['position' => $position]);
                }
            }
        });

        return back();
    }
}
