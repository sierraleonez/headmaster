<?php

namespace App\Services;

use App\Models\BoardColumn;
use App\Models\Item;
use App\Models\LogEntry;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Materialises a reviewed draft plan into real projects, columns and cards.
 */
class PlanApplier
{
    public function __construct(private readonly ProjectTree $tree) {}

    /**
     * Create the drafted project as a sub-project card on the target board.
     *
     * @param  array<string, mixed>  $plan
     */
    public function apply(User $user, Project $target, array $plan, ?int $columnId = null): Project
    {
        abort_unless($target->user_id === $user->id, 403);
        abort_unless($target->isBoard(), 422, 'A plan can only be added to a board.');

        $plan = $this->normalise($plan);

        return DB::transaction(function () use ($user, $target, $plan, $columnId) {
            $column = $this->resolveColumn($target, $columnId);

            $card = Item::create([
                'project_id' => $target->id,
                'board_column_id' => $column->id,
                'title' => $plan['name'],
                'body' => $plan['description'],
                'type' => Item::TYPE_SUBPROJECT,
                'position' => (int) $column->items()->max('position') + 1,
            ]);

            return $this->build($user, $card, $plan);
        });
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function build(User $user, Item $card, array $plan): Project
    {
        $project = $this->tree->createProject(
            $user,
            $card,
            $plan['name'],
            $plan['kind'],
            $plan['description'],
        );

        if ($plan['kind'] === Project::KIND_LOG) {
            foreach ($plan['entries'] as $entry) {
                LogEntry::create([
                    'project_id' => $project->id,
                    'logged_on' => $entry['logged_on'],
                    'title' => $entry['title'],
                    'body' => $entry['body'],
                ]);
            }

            return $project;
        }

        // createProject seeded the defaults; a plan with its own columns replaces them.
        if ($plan['columns'] !== []) {
            $project->columns()->delete();

            foreach ($plan['columns'] as $position => $name) {
                BoardColumn::create([
                    'project_id' => $project->id,
                    'name' => $name,
                    'position' => $position,
                ]);
            }
        }

        $columns = $project->columns()->get();
        $fallback = $columns->first();

        foreach ($plan['items'] as $index => $item) {
            $column = $columns->firstWhere(
                fn (BoardColumn $candidate) => mb_strtolower($candidate->name) === mb_strtolower($item['column'])
            ) ?? $fallback;

            $card = Item::create([
                'project_id' => $project->id,
                'board_column_id' => $column->id,
                'title' => $item['title'],
                'body' => $item['body'],
                'type' => $item['type'],
                'position' => $index,
            ]);

            if ($item['type'] === Item::TYPE_SUBPROJECT && $item['project'] !== null) {
                $this->build($user, $card, $item['project']);
            }
        }

        return $project;
    }

    private function resolveColumn(Project $target, ?int $columnId): BoardColumn
    {
        if ($columnId) {
            $column = $target->columns()->whereKey($columnId)->first();

            if ($column) {
                return $column;
            }
        }

        $first = $target->columns()->first();

        if ($first) {
            return $first;
        }

        $this->tree->seedDefaultColumns($target);

        return $target->columns()->firstOrFail();
    }

    /**
     * Reduce arbitrary model output to exactly the shape the builder expects.
     *
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public function normalise(array $plan, int $depth = 0): array
    {
        $kind = ($plan['kind'] ?? null) === Project::KIND_LOG
            ? Project::KIND_LOG
            : Project::KIND_BOARD;

        $normalised = [
            'name' => $this->text($plan['name'] ?? null, 'Untitled') ?? 'Untitled',
            'kind' => $kind,
            'description' => $this->text($plan['description'] ?? null),
            'columns' => [],
            'items' => [],
            'entries' => [],
        ];

        if ($kind === Project::KIND_LOG) {
            foreach ($this->list($plan['entries'] ?? null) as $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $normalised['entries'][] = [
                    'logged_on' => $this->date($entry['logged_on'] ?? null),
                    'title' => $this->text($entry['title'] ?? null, 'Entry') ?? 'Entry',
                    'body' => $this->text($entry['body'] ?? null),
                ];
            }

            return $normalised;
        }

        foreach ($this->list($plan['columns'] ?? null) as $name) {
            $name = $this->text($name);

            if ($name !== null) {
                $normalised['columns'][] = $name;
            }
        }

        foreach ($this->list($plan['items'] ?? null) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $child = is_array($item['project'] ?? null) && $depth < 6
                ? $this->normalise($item['project'], $depth + 1)
                : null;

            $type = ($item['type'] ?? null) === Item::TYPE_SUBPROJECT && $child !== null
                ? Item::TYPE_SUBPROJECT
                : Item::TYPE_NOTE;

            $normalised['items'][] = [
                'title' => $this->text($item['title'] ?? null, 'Untitled card') ?? 'Untitled card',
                'body' => $this->text($item['body'] ?? null),
                'column' => $this->text($item['column'] ?? null, 'Backlog') ?? 'Backlog',
                'type' => $type,
                'project' => $type === Item::TYPE_SUBPROJECT ? $child : null,
            ];
        }

        return $normalised;
    }

    private function text(mixed $value, ?string $default = null): ?string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = trim($value);

        return $value === '' ? $default : mb_substr($value, 0, 65000);
    }

    private function date(mixed $value): string
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($value))) {
            return trim($value);
        }

        return now()->toDateString();
    }

    /**
     * @return list<mixed>
     */
    private function list(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }
}
