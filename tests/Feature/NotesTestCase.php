<?php

namespace Tests\Feature;

use App\Models\Node;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class NotesTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Node $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->root = Node::create(['user_id' => $this->admin->id, 'title' => 'Notes', 'is_public' => true]);
    }

    protected function user(): User
    {
        $user = User::factory()->create();
        $user->assignRole('user');

        return $user;
    }

    protected function node(User $owner, ?Node $parent = null, bool $public = false, string $title = 'Node'): Node
    {
        return Node::create([
            'user_id' => $owner->id,
            'parent_id' => ($parent ?? $this->root)->id,
            'title' => $title,
            'is_public' => $public,
        ]);
    }

    protected function topic(User $owner, Node $node, bool $public = false, string $title = 'Topic', string $content = ''): Topic
    {
        $topic = Topic::create([
            'user_id' => $owner->id,
            'title' => $title,
            'content' => $content,
            'is_public' => $public,
        ]);
        $topic->nodes()->attach($node->id, ['is_link' => false]);

        return $topic;
    }
}
