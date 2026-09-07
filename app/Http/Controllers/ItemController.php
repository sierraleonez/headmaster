<?php

namespace App\Http\Controllers;

use App\Models\BoardColumn;
use App\Models\Item;
use App\Services\ProjectTree;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function __construct(private readonly ProjectTree $tree) {}

    public function store(Request $request, BoardColumn $column): RedirectResponse
    {
        $this->authorize('update', $column);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        Item::create([
            'project_id' => $column->project_id,
            'board_column_id' => $column->id,
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'type' => Item::TYPE_NOTE,
            'position' => (int) $column->items()->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        $item->update($validated);

        // Keep the sub-project's name in step with the card that opens it.
        if (isset($validated['title']) && $item->child) {
            $item->child->update(['name' => $validated['title']]);
        }

        return back();
    }

    public function destroy(Item $item): RedirectResponse
    {
        $this->authorize('delete', $item);

        $this->tree->deleteItem($item);

        return back();
    }

    /**
     * Move a card to a position in a column. Any card may go anywhere: the
     * board imposes no workflow rules.
     */
    public function move(Request $request, Item $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'board_column_id' => ['required', 'integer', 'exists:board_columns,id'],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $target = BoardColumn::findOrFail($request->integer('board_column_id'));
        $this->authorize('update', $target);

        // Cards never move between boards.
        abort_unless($target->project_id === $item->project_id, 403);

        DB::transaction(function () use ($item, $target, $validated) {
            $source = $item->board_column_id;

            $item->update(['board_column_id' => $target->id]);

            $ids = $this->orderedIds($target, except: $item->id);

            $at = min((int) $validated['position'], count($ids));
            array_splice($ids, $at, 0, [$item->id]);

            $this->writePositions($ids);

            $sourceColumn = BoardColumn::find($source);

            if ($sourceColumn && $sourceColumn->id !== $target->id) {
                $this->writePositions($this->orderedIds($sourceColumn));
            }
        });

        return back();
    }

    /**
     * Card ids in one column, in board order.
     *
     * @return list<int>
     */
    private function orderedIds(BoardColumn $column, ?int $except = null): array
    {
        $ids = array_map(intval(...), $column->items()->pluck('id')->all());

        return array_values(array_filter($ids, fn (int $id) => $id !== $except));
    }

    /**
     * @param  list<int>  $ids
     */
    private function writePositions(array $ids): void
    {
        foreach ($ids as $position => $id) {
            Item::where('id', $id)->update(['position' => $position]);
        }
    }
}
