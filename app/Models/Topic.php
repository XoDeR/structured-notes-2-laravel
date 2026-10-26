<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $user_id
 * @property string $title
 * @property string $content
 * @property bool $is_public
 */
#[Fillable(['user_id', 'title', 'content', 'is_public'])]
class Topic extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    public const ATTACHMENTS = 'attachments';

    public const IMAGES = 'images';

    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ATTACHMENTS);
        $this->addMediaCollection(self::IMAGES);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function nodes(): BelongsToMany
    {
        return $this->belongsToMany(Node::class)
            ->using(NodeTopic::class)
            ->withPivot(['id', 'is_link'])
            ->withTimestamps();
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class);
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->using(Favorite::class)->withTimestamps();
    }

    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->can('manage all content')) {
            return;
        }

        $query->where(fn (Builder $q) => $q->where('topics.is_public', true)->orWhere('topics.user_id', $user->id));
    }

    /**
     * @param  list<string>  $values
     */
    public function syncTags(array $values): void
    {
        $values = collect($values)->map(fn ($v) => trim((string) $v))->filter()->unique()->values();

        $this->tags()->whereNotIn('value', $values)->delete();

        $existing = $this->tags()->pluck('value');
        foreach ($values->diff($existing) as $value) {
            $this->tags()->create(['value' => $value]);
        }
    }
}
