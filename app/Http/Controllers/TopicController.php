<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Topic;
use App\Support\HtmlCleaner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TopicController extends Controller
{
    public function __construct(private readonly HtmlCleaner $cleaner) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request) + $request->validate(['node_id' => ['required', 'uuid', 'exists:nodes,id']]);

        $node = Node::query()->findOrFail($data['node_id']);
        $this->authorize('createChild', $node);
        $this->assertVisibility($data['is_public'], [$node]);

        $topic = DB::transaction(function () use ($request, $data, $node) {
            $topic = Topic::query()->create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'content' => $this->cleaner->clean($data['content'] ?? ''),
                'is_public' => $data['is_public'],
            ]);
            $topic->nodes()->attach($node->id, ['is_link' => false]);
            $topic->syncTags($data['tags'] ?? []);

            return $topic;
        });

        return to_route('notes.index', ['node' => $node->id, 'topic' => $topic->id]);
    }

    public function update(Request $request, Topic $topic): RedirectResponse
    {
        $this->authorize('update', $topic);

        $data = $this->validated($request);
        $this->assertVisibility($data['is_public'], $topic->nodes);

        DB::transaction(function () use ($topic, $data) {
            $topic->update([
                'title' => $data['title'],
                'content' => $this->cleaner->clean($data['content'] ?? ''),
                'is_public' => $data['is_public'],
            ]);
            $topic->syncTags($data['tags'] ?? []);
        });

        return to_route('notes.index', array_filter(['node' => $request->query('node'), 'topic' => $topic->id]));
    }

    /**
     * Deletes the topic, or only detaches it from the given node when it is a link there.
     */
    public function destroy(Request $request, Topic $topic): RedirectResponse
    {
        $nodeId = $request->validate(['node' => ['required', 'uuid']])['node'];
        $user = $request->user();

        $placement = $topic->nodes()->where('nodes.id', $nodeId)->firstOrFail();

        if ($placement->pivot->is_link) {
            abort_unless(
                $user->can('update', $topic) || $placement->user_id === $user->id || $user->can('manage all content'),
                403,
            );
            $topic->nodes()->detach($nodeId);
        } else {
            $this->authorize('delete', $topic);
            $topic->delete();
        }

        return to_route('notes.index', ['node' => $nodeId]);
    }

    /**
     * Paste a cut/copied topic into another node as a move, a copy or a link.
     */
    public function paste(Request $request, Topic $topic): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['move', 'copy', 'link'])],
            'node_id' => ['required', 'uuid', 'exists:nodes,id'],
            'from_node_id' => ['required_if:mode,move', 'nullable', 'uuid'],
        ]);

        $this->authorize('view', $topic);
        $target = Node::query()->findOrFail($data['node_id']);
        $this->authorize('createChild', $target);
        $this->assertVisibility($topic->is_public, [$target]);

        $result = match ($data['mode']) {
            'move' => $this->move($topic, $target, $data['from_node_id']),
            'copy' => $this->copy($request, $topic, $target, $data['from_node_id'] ?? null),
            'link' => $this->link($topic, $target),
        };

        return to_route('notes.index', ['node' => $target->id, 'topic' => $result->id]);
    }

    private function move(Topic $topic, Node $target, string $fromId): Topic
    {
        $this->authorize('update', $topic);

        $from = $topic->nodes()->where('nodes.id', $fromId)->firstOrFail();

        DB::transaction(function () use ($topic, $target, $from) {
            $topic->nodes()->detach($from->id);
            $topic->nodes()->syncWithoutDetaching([$target->id => ['is_link' => $from->pivot->is_link]]);
        });

        return $topic;
    }

    private function link(Topic $topic, Node $target): Topic
    {
        $topic->nodes()->syncWithoutDetaching([$target->id => ['is_link' => true]]);

        return $topic;
    }

    private function copy(Request $request, Topic $topic, Node $target, ?string $fromId): Topic
    {
        return DB::transaction(function () use ($request, $topic, $target, $fromId) {
            $copy = Topic::query()->create([
                'user_id' => $request->user()->id,
                'title' => $fromId === $target->id ? $topic->title.' (copy)' : $topic->title,
                'content' => $topic->content,
                'is_public' => $topic->is_public,
            ]);
            $copy->nodes()->attach($target->id, ['is_link' => false]);
            $copy->syncTags($topic->tags()->pluck('value')->all());

            // Copied files get new ids, so the urls embedded in the content have to follow them.
            $content = $topic->content;
            foreach ([Topic::IMAGES, Topic::ATTACHMENTS] as $collection) {
                foreach ($topic->getMedia($collection) as $media) {
                    $new = $media->copy($copy, $collection);
                    $content = str_replace('/media/'.$media->id, '/media/'.$new->id, $content);
                }
            }
            $copy->update(['content' => $content]);

            return $copy;
        });
    }

    /**
     * @return array{title: string, content: ?string, is_public: bool, tags: list<string>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'is_public' => ['boolean'],
            'tags' => ['array', 'max:50'],
            'tags.*' => ['nullable', 'string', 'max:100'],
        ]);

        $data['is_public'] = (bool) ($data['is_public'] ?? false);
        $data['tags'] ??= [];

        return $data;
    }

    /**
     * @param  iterable<Node>  $nodes
     */
    private function assertVisibility(bool $isPublic, iterable $nodes): void
    {
        if (! $isPublic) {
            return;
        }

        foreach ($nodes as $node) {
            if (! $node->is_public) {
                throw ValidationException::withMessages([
                    'is_public' => 'A public topic cannot be placed in a private node.',
                ]);
            }
        }
    }
}
