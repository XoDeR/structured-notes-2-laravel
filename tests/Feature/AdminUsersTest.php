<?php

namespace Tests\Feature;

use App\Models\User;

class AdminUsersTest extends NotesTestCase
{
    public function test_only_admins_can_manage_users(): void
    {
        $this->actingAs($this->user())->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/Users')->has('users', 2));
    }

    public function test_admin_creates_a_verified_user_with_a_role(): void
    {
        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'user',
        ])->assertRedirect();

        $user = User::where('email', 'new@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole('user'));
        $this->assertFalse($user->can('manage users'));
    }

    public function test_admin_can_disable_a_user_who_is_then_signed_out(): void
    {
        $user = $this->user();

        $this->actingAs($this->admin)->patch(route('admin.users.update', $user), [
            'name' => $user->name, 'email' => $user->email, 'role' => 'user', 'disabled' => true,
        ])->assertRedirect();

        $this->assertNotNull($user->fresh()->disabled_at);
        $this->actingAs($user->fresh())->get(route('notes.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_cannot_demote_disable_or_delete_themselves(): void
    {
        $this->actingAs($this->admin)->patch(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name, 'email' => $this->admin->email, 'role' => 'user',
        ])->assertSessionHasErrors('role');

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->admin))->assertUnprocessable();
    }

    public function test_deleting_a_user_keeps_other_peoples_topics_in_their_nodes(): void
    {
        $leaver = $this->user();
        $stayer = $this->user();
        $node = $this->node($leaver, public: true);
        $theirs = $this->topic($stayer, $node, public: true);
        $own = $this->topic($leaver, $node, public: true);

        $this->actingAs($this->admin)->delete(route('admin.users.destroy', $leaver))->assertRedirect();

        $this->assertNull(User::find($leaver->id));
        $this->assertDatabaseMissing('topics', ['id' => $own->id]);
        $this->assertDatabaseHas('node_topic', ['topic_id' => $theirs->id, 'node_id' => $node->id]);
        $this->assertSame($this->admin->id, $node->fresh()->user_id);
    }
}
