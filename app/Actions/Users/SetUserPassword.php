<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetUserPassword
{
    public function handle(User $user, string $password, bool $mustChange): void
    {
        DB::transaction(function () use ($user, $password, $mustChange): void {
            $user->forceFill([
                'password' => $password,
                'must_change_password' => $mustChange,
            ])->save();

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
