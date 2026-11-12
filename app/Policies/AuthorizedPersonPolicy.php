<?php

namespace App\Policies;

use App\Models\AuthorizedPerson;
use App\Models\User;

class AuthorizedPersonPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AuthorizedPerson $person): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageCards();
    }

    public function update(User $user, AuthorizedPerson $person): bool
    {
        return $user->canManageCards();
    }

    public function delete(User $user, AuthorizedPerson $person): bool
    {
        return $user->canManageCards();
    }
}
