<?php

namespace App\Queries;

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ClientSearch
{
    /** @param Builder<Client> $clients */
    public function apply(Builder $clients, ?string $text, ?string $dniHash): void
    {
        if ($dniHash === null && ($text === null || $text === '')) {
            return;
        }

        $clients->where(function (Builder $query) use ($text, $dniHash): void {
            $this->constrain($query, $text, $dniHash);
            $query->orWhereHas('authorizedPeople', function (Builder $people) use ($text, $dniHash): void {
                $this->constrain($people, $text, $dniHash);
            });
        });
    }

    /**
     * @param  Collection<int, Client>  $clients
     * @return list<int>
     */
    public function matchedPersonIds(Collection $clients, ?string $text, ?string $dniHash): array
    {
        if ($dniHash === null && ($text === null || $text === '')) {
            return [];
        }

        $phone = Phone::normalize($text ?? '');
        $ids = [];
        foreach ($clients as $client) {
            foreach ($client->authorizedPeople as $person) {
                $matches = match (true) {
                    $dniHash !== null => $person->dni_hash === $dniHash,
                    preg_match('/^\d{8}$/', $phone) === 1 => $person->phone === $phone,
                    default => Str::contains(Str::lower($person->full_name), Str::lower($text)),
                };
                if ($matches) {
                    $ids[] = $person->id;
                }
            }
        }

        return $ids;
    }

    /** @param Builder<Client>|Builder<AuthorizedPerson> $query */
    private function constrain(Builder $query, ?string $text, ?string $dniHash): void
    {
        if ($dniHash !== null) {
            $query->where('dni_hash', $dniHash);

            return;
        }

        $phone = Phone::normalize($text ?? '');
        if (preg_match('/^\d{8}$/', $phone)) {
            $query->where('phone', $phone);

            return;
        }

        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], Str::lower($text ?? ''));
        $query->whereRaw("LOWER(full_name) LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
    }
}
