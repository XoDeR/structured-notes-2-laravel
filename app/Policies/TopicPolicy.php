<?php

namespace App\Policies;

use App\Models\Topic;
use App\Models\User;

class TopicPolicy
{
    public function view(?User $user, Topic $topic): bool
    {
        return $topic->is_public || ($user !== null && $this->owns($user, $topic));
    }

    public function update(User $user, Topic $topic): bool
    {
        return $this->owns($user, $topic);
    }

    public function delete(User $user, Topic $topic): bool
    {
        return $this->owns($user, $topic);
    }

    private function owns(User $user, Topic $topic): bool
    {
        return $topic->user_id === $user->id || $user->can('manage all content');
    }
}
