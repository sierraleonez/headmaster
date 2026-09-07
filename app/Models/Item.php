<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property int $board_column_id
 * @property string $title
 * @property string|null $body
 * @property string $type
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Project|null $child
 * @property-read BoardColumn $column
 * @property-read Project $project
 */
class Item extends Model
{
    public const TYPE_NOTE = 'note';

    public const TYPE_SUBPROJECT = 'subproject';

    protected $fillable = [
        'project_id',
        'board_column_id',
        'title',
        'body',
        'type',
        'position',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<BoardColumn, $this> */
    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    /**
     * The sub-project this card opens into, when it is a sub-project card.
     *
     * @return HasOne<Project, $this>
     */
    public function child(): HasOne
    {
        return $this->hasOne(Project::class, 'parent_item_id');
    }

    public function isSubproject(): bool
    {
        return $this->type === self::TYPE_SUBPROJECT;
    }
}
