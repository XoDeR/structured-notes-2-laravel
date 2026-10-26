<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $user_id
 * @property string $title
 * @property bool $is_public
 */
#[Fillable(['parent_id', 'user_id', 'title', 'is_public'])]
class Node extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class)
            ->using(NodeTopic::class)
            ->withPivot(['id', 'is_link'])
            ->withTimestamps();
    }

    /**
     * Nodes the given user is allowed to see: public ones, their own, or everything for content managers.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('manage all content')) {
            return;
        }

        $query->where(fn (Builder $q) => $q->where('nodes.is_public', true)->orWhere('nodes.user_id', $user->id));
    }

    /**
     * Ids of this node and every node below it.
     *
     * @return list<string>
     */
    public function subtreeIds(): array
    {
        $rows = self::query()->toBase()->get(['id', 'parent_id'])->groupBy('parent_id');

        $ids = [];
        $queue = [$this->id];
        while ($queue) {
            $id = array_shift($queue);
            $ids[] = $id;
            foreach ($rows->get($id, []) as $child) {
                $queue[] = $child->id;
            }
        }

        return $ids;
    }

    public function isDescendantOf(self $other): bool
    {
        return in_array($this->id, $other->subtreeIds(), true);
    }
}
