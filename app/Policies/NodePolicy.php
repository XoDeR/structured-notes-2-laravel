<?php

namespace App\Policies;

use App\Models\Node;
use App\Models\User;

class NodePolicy
{
    public function view(User $user, Node $node): bool
    {
        return $node->is_public || $this->owns($user, $node);
    }

    /** Creating a child under $node. */
    public function createChild(User $user, Node $node): bool
    {
        return $this->view($user, $node);
    }

    public function update(User $user, Node $node): bool
    {
        // The root node is structural and cannot be edited, moved or removed.
        return $node->parent_id !== null && $this->owns($user, $node);
    }

    public function delete(User $user, Node $node): bool
    {
        return $this->update($user, $node);
    }

    private function owns(User $user, Node $node): bool
    {
        return $node->user_id === $user->id || $user->can('manage all content');
    }
}
