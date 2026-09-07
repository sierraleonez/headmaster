<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $parent_item_id
 * @property string $name
 * @property string|null $description
 * @property string $kind
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BoardColumn> $columns
 * @property-read Collection<int, LogEntry> $logEntries
 * @property-read Item|null $parentItem
 * @property-read User $user
 */
class Project extends Model
{
    public const KIND_BOARD = 'board';

    public const KIND_LOG = 'log';

    /**
     * Columns every new board starts with. Fully editable afterwards.
     */
    public const DEFAULT_COLUMNS = [
        'Backlog',
        'Todo',
        'In Progress',
        'Blocked',
        'Canceled',
        'Done',
    ];

    protected $fillable = [
        'user_id',
        'parent_item_id',
        'name',
        'description',
        'kind',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The card in the parent board that this sub-project hangs off.
     *
     * @return BelongsTo<Item, $this>
     */
    public function parentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'parent_item_id');
    }

    /** @return HasMany<BoardColumn, $this> */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** @return HasMany<LogEntry, $this> */
    public function logEntries(): HasMany
    {
        return $this->hasMany(LogEntry::class)->orderByDesc('logged_on')->orderByDesc('id');
    }

    public function isBoard(): bool
    {
        return $this->kind === self::KIND_BOARD;
    }

    public function isRoot(): bool
    {
        return $this->parent_item_id === null;
    }

    /**
     * Root project first, this project last.
     *
     * @return list<Project>
     */
    public function ancestors(): array
    {
        $chain = [];
        $project = $this;

        while ($project !== null) {
            array_unshift($chain, $project);

            $parentItem = $project->parent_item_id
                ? Item::find($project->parent_item_id)
                : null;

            $project = $parentItem?->project;
        }

        return $chain;
    }
}
