<?php

namespace App\Enums;

use App\Models\AuthorizedPerson;
use App\Models\Client;

enum PhotoOwner: string
{
    case Client = 'client';
    case Person = 'person';

    public function directory(): string
    {
        return match ($this) {
            self::Client => 'clients',
            self::Person => 'people',
        };
    }

    /** @return class-string<Client|AuthorizedPerson> */
    public function modelClass(): string
    {
        return match ($this) {
            self::Client => Client::class,
            self::Person => AuthorizedPerson::class,
        };
    }
}
