<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Tag;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NotesController extends Controller
{
    private const SEARCH_LIMIT = 200;

    public function index(Request $request): Response
    {
        $user = $request->user();

        $this->ensureRootExists($user);

        $nodes = Node::query()->visibleTo($user)->orderBy('title')->get();
        $root = $nodes->firstWhere('parent_id', null);

        $nodeId = $nodes->contains('id', $request->query('node')) ? $request->query('node') : $root?->id;
        $favoriteIds = $user->favoriteTopics()->pluck('topics.id')->all();

        $search = $this->search($request, $user, $nodes, $favoriteIds);

        return Inertia::render('notes/Index', [
            'tree' => $nodes->map(fn (Node $n) => [
                'id' => $n->id,
                'parent_id' => $n->parent_id,
                'title' => $n->title,
                'is_public' => $n->is_public,
                'can_edit' => $user->can('update', $n),
            ])->values(),
            'nodeId' => $nodeId,
            'topics' => $nodeId ? $this->topicsOf($nodes->firstWhere('id', $nodeId), $user, $favoriteIds) : [],
            'topic' => $this->topicDetail($request->query('topic'), $nodeId, $user),
            'search' => $search,
            'knownTags' => fn () => Tag::query()
                ->whereHas('topic', fn ($q) => $q->visibleTo($user))
                ->distinct()->orderBy('value')->pluck('value'),
        ]);
    }

    private function ensureRootExists(User $user): void
    {
        if (! Node::query()->whereNull('parent_id')->exists()) {
            Node::query()->create(['user_id' => $user->id, 'title' => 'Notes', 'is_public' => true]);
        }
    }

    private function topicsOf(?Node $node, User $user, array $favoriteIds): Collection
    {
        if (! $node) {
            return collect();
        }

        return $node->topics()->visibleTo($user)->orderBy('topics.title')->get()->map(fn (Topic $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'is_public' => $t->is_public,
            'is_link' => (bool) $t->pivot->is_link,
            'is_owner' => $user->can('update', $t),
            'is_favorite' => in_array($t->id, $favoriteIds, true),
        ])->values();
    }

    private function topicDetail(?string $id, ?string $nodeId, User $user): ?array
    {
        if (! $id || ! Str::isUuid($id)) {
            return null;
        }

        $topic = Topic::query()->visibleTo($user)->with(['tags', 'media', 'owner:id,name'])->find($id);
        if (! $topic) {
            return null;
        }

        $isLink = $nodeId
            ? (bool) $topic->nodes()->where('nodes.id', $nodeId)->first()?->pivot->is_link
            : false;

        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'content' => $topic->content,
            'is_public' => $topic->is_public,
            'is_link' => $isLink,
            'can_edit' => $user->can('update', $topic) && ! $isLink,
            'owner' => $topic->owner->name,
            'updated_at' => $topic->updated_at,
            'share_url' => $topic->is_public ? route('topics.show', $topic) : null,
            'tags' => $topic->tags->pluck('value')->sort()->values(),
            'attachments' => $topic->getMedia(Topic::ATTACHMENTS)->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->file_name,
                'size' => $m->size,
                'url' => route('media.show', $m),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function search(Request $request, User $user, Collection $nodes, array $favoriteIds): ?array
    {
        $q = trim((string) $request->query('q', ''));
        $tag = trim((string) $request->query('tag', ''));
        $fav = $request->boolean('fav');

        if ($q === '' && $tag === '' && ! $fav) {
            return null;
        }

        $mode = $request->query('mode') === 'all' ? 'all' : 'phrase';
        $titleOnly = $request->boolean('title_only');

        $query = Topic::query()->visibleTo($user)
            ->with(['nodes' => fn ($n) => $n->visibleTo($user)])
            ->orderBy('title')
            ->limit(self::SEARCH_LIMIT);

        if ($fav) {
            $query->whereIn('topics.id', $favoriteIds);
        }

        if ($tag !== '') {
            $query->whereHas('tags', fn ($t) => $t->where('value', $tag));
        }

        if ($q !== '') {
            $terms = $mode === 'all' ? preg_split('/\s+/', $q, -1, PREG_SPLIT_NO_EMPTY) : [$q];
            foreach ($terms as $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $query->where(fn ($w) => $titleOnly
                    ? $w->where('title', 'ilike', $like)
                    : $w->where('title', 'ilike', $like)->orWhere('content', 'ilike', $like));
            }
        }

        $byId = $nodes->keyBy('id');
        $path = function (?string $id) use ($byId): string {
            $parts = [];
            while ($id && $byId->has($id)) {
                array_unshift($parts, $byId[$id]->title);
                $id = $byId[$id]->parent_id;
            }

            return implode(' › ', $parts);
        };

        $results = $query->get()->flatMap(fn (Topic $t) => $t->nodes->map(fn (Node $n) => [
            'topic_id' => $t->id,
            'node_id' => $n->id,
            'title' => $t->title,
            'path' => $path($n->id),
        ]))->values();

        return [
            'q' => $q,
            'tag' => $tag,
            'fav' => $fav,
            'mode' => $mode,
            'title_only' => $titleOnly,
            'results' => $results,
        ];
    }
}
