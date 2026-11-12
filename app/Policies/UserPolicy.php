<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $subject): bool
    {
        return $user->isOwner() && ! $subject->isOwner();
    }

    public function delete(User $user, User $subject): bool
    {
        return $user->isOwner() && ! $subject->isOwner();
    }
}
