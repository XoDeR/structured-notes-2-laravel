<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\Topic;
use Illuminate\Support\Str;

class NodeTest extends NotesTestCase
{
    public function test_tree_only_contains_nodes_visible_to_the_user(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $this->node($alice, public: true, title: 'Shared');
        $this->node($alice, title: 'Alice private');
        $this->node($bob, title: 'Bob private');

        $this->actingAs($bob)->get(route('notes.index'))
            ->assertInertia(fn ($page) => $page
                ->component('notes/Index')
                ->where('tree', fn ($tree) => collect($tree)->pluck('title')->sort()->values()->all() === ['Bob private', 'Notes', 'Shared']));
    }

    public function test_admin_sees_every_node(): void
    {
        $this->node($this->user(), title: 'Someone private');

        $this->actingAs($this->admin)->get(route('notes.index'))
            ->assertInertia(fn ($page) => $page->has('tree', 2));
    }

    public function test_user_can_create_private_node_and_it_gets_selected(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)->post(route('nodes.store'), [
            'parent_id' => $this->root->id,
            'title' => 'Ideas',
            'is_public' => false,
        ]);

        $node = Node::where('title', 'Ideas')->firstOrFail();
        $response->assertRedirect(route('notes.index', ['node' => $node->id]));
        $this->assertSame($user->id, $node->user_id);
    }

    public function test_public_node_cannot_be_added_to_a_private_node(): void
    {
        $user = $this->user();
        $private = $this->node($user);

        $this->actingAs($user)->post(route('nodes.store'), [
            'parent_id' => $private->id,
            'title' => 'Nope',
            'is_public' => true,
        ])->assertSessionHasErrors('is_public');
    }

    public function test_cannot_create_under_somebody_elses_private_node(): void
    {
        $private = $this->node($this->user());

        $this->actingAs($this->user())->post(route('nodes.store'), [
            'parent_id' => $private->id,
            'title' => 'Intruder',
        ])->assertForbidden();
    }

    public function test_only_owner_can_rename_and_the_root_is_protected(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);

        $this->actingAs($this->user())->patch(route('nodes.update', $node), ['title' => 'Hacked'])->assertForbidden();
        $this->actingAs($alice)->patch(route('nodes.update', $node), ['title' => 'Renamed', 'is_public' => true])->assertRedirect();
        $this->assertSame('Renamed', $node->fresh()->title);

        $this->actingAs($this->admin)->patch(route('nodes.update', $this->root), ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->delete(route('nodes.destroy', $this->root))->assertForbidden();
    }

    public function test_node_with_public_content_cannot_become_private(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $this->topic($alice, $node, public: true);

        $this->actingAs($alice)->patch(route('nodes.update', $node), ['title' => 'x', 'is_public' => false])
            ->assertSessionHasErrors('is_public');
    }

    public function test_moving_into_own_descendant_is_rejected(): void
    {
        $alice = $this->user();
        $parent = $this->node($alice, public: true);
        $child = $this->node($alice, $parent, public: true);

        $this->actingAs($alice)->patch(route('nodes.move', $parent), ['parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');

        $other = $this->node($alice, public: true);
        $this->actingAs($alice)->patch(route('nodes.move', $parent), ['parent_id' => $other->id])->assertRedirect();
        $this->assertSame($other->id, $parent->fresh()->parent_id);
    }

    public function test_deleting_a_node_removes_its_topics_but_keeps_linked_ones(): void
    {
        $alice = $this->user();
        $branch = $this->node($alice, public: true);
        $inner = $this->node($alice, $branch, public: true);
        $owned = $this->topic($alice, $inner, public: true);

        $elsewhere = $this->node($alice, public: true);
        $kept = $this->topic($alice, $elsewhere, public: true);
        $kept->nodes()->attach($inner->id, ['is_link' => true]);

        $this->actingAs($alice)->delete(route('nodes.destroy', $branch))
            ->assertRedirect(route('notes.index', ['node' => $this->root->id]));

        $this->assertNull(Node::find($inner->id));
        $this->assertNull(Topic::find($owned->id));
        $this->assertNotNull(Topic::find($kept->id));
        $this->assertCount(1, $kept->fresh()->nodes);
    }

    public function test_primary_keys_are_uuids(): void
    {
        $this->assertTrue(Str::isUuid($this->root->id));
        $this->assertTrue(Str::isUuid($this->admin->id));
    }
}
