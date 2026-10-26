<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NodeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'parent_id' => ['required', 'uuid', 'exists:nodes,id'],
            'title' => ['required', 'string', 'max:255'],
            'is_public' => ['boolean'],
        ]);

        $parent = Node::query()->findOrFail($data['parent_id']);
        $this->authorize('createChild', $parent);

        $isPublic = (bool) ($data['is_public'] ?? false);
        if ($isPublic && ! $parent->is_public) {
            throw ValidationException::withMessages(['is_public' => 'A public node cannot be added to a private node.']);
        }

        $node = Node::query()->create([
            'parent_id' => $parent->id,
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'is_public' => $isPublic,
        ]);

        return to_route('notes.index', ['node' => $node->id]);
    }

    public function update(Request $request, Node $node): RedirectResponse
    {
        $this->authorize('update', $node);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_public' => ['boolean'],
        ]);

        $isPublic = (bool) ($data['is_public'] ?? false);

        if ($isPublic && ! $node->parent->is_public) {
            throw ValidationException::withMessages(['is_public' => 'A public node cannot be added to a private node.']);
        }

        if (! $isPublic && $node->is_public) {
            $hasPublicContent = $node->children()->where('is_public', true)->exists()
                || $node->topics()->where('topics.is_public', true)->exists();

            if ($hasPublicContent) {
                throw ValidationException::withMessages(['is_public' => 'This node contains public nodes or topics, make them private first.']);
            }
        }

        $node->update(['title' => $data['title'], 'is_public' => $isPublic]);

        return to_route('notes.index', array_filter(['node' => $node->id, 'topic' => $request->query('topic')]));
    }

    public function move(Request $request, Node $node): RedirectResponse
    {
        $this->authorize('update', $node);

        $data = $request->validate(['parent_id' => ['required', 'uuid', 'exists:nodes,id']]);
        $target = Node::query()->findOrFail($data['parent_id']);
        $this->authorize('createChild', $target);

        if ($target->isDescendantOf($node)) {
            throw ValidationException::withMessages(['parent_id' => 'A node cannot be moved into itself or one of its descendants.']);
        }

        if ($node->is_public && ! $target->is_public) {
            throw ValidationException::withMessages(['parent_id' => 'A public node cannot be added to a private node.']);
        }

        $node->update(['parent_id' => $target->id]);

        return to_route('notes.index', ['node' => $node->id]);
    }

    public function destroy(Node $node): RedirectResponse
    {
        $this->authorize('delete', $node);

        $parentId = $node->parent_id;
        $subtree = $node->subtreeIds();

        // Topics living in the removed branch are deleted with their files; linked topics are only detached.
        Topic::query()
            ->whereHas('nodes', fn ($q) => $q->whereIn('nodes.id', $subtree)->where('node_topic.is_link', false))
            ->get()
            ->each->delete();

        $node->delete();

        return to_route('notes.index', ['node' => $parentId]);
    }
}
