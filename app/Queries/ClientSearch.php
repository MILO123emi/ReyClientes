<?php

namespace App\Queries;

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Support\Phone;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

class ClientSearch
{
    public function query(?string $text, ?string $dniHash): Builder
    {
        return DB::query()->fromSub(
            $this->people('clients', 0, $text, $dniHash)->unionAll(
                $this->people('authorized_people', 1, $text, $dniHash),
            ), 'matches',
        );
    }

    /** @return array{owners: int, authorized: int} */
    public function counts(?string $text, ?string $dniHash): array
    {
        $counts = $this->query($text, $dniHash)->selectRaw('role_order, COUNT(*) as total')->groupBy('role_order')->pluck('total', 'role_order');

        return ['owners' => (int) ($counts[0] ?? 0), 'authorized' => (int) ($counts[1] ?? 0)];
    }

    /**
     * @param  array<string, string|int>  $parameters
     * @return LengthAwarePaginator<int|string, array{role: string, match: string, client: Client, person: AuthorizedPerson|null}>
     */
    public function paginate(?string $text, ?string $dniHash, string $role, array $parameters): LengthAwarePaginator
    {
        $query = $this->query($text, $dniHash);
        if ($role !== 'todos') {
            $query->where('role_order', $role === 'duenos' ? 0 : 1);
        }
        $page = $query->orderBy('role_order')->orderBy('match_rank')->orderBy('search_name')->orderBy('id')->paginate(20)->appends($parameters);
        $rows = $page->getCollection();
        $clients = Client::query()->with('authorizedPeople')->withCount('authorizedPeople')
            ->whereIn('id', $rows->where('role_order', 0)->pluck('id'))->get()->keyBy('id');
        $people = AuthorizedPerson::query()->with(['client' => fn ($query) => $query->with('authorizedPeople')->withCount('authorizedPeople')])
            ->whereIn('id', $rows->where('role_order', 1)->pluck('id'))->get()->keyBy('id');

        return $page->through(function (stdClass $row) use ($clients, $people): array {
            $person = (int) $row->role_order === 1 ? $people->findOrFail($row->id) : null;
            $client = $person !== null ? $person->client : $clients->findOrFail($row->id);

            return [
                'role' => $person !== null ? 'authorized' : 'owner',
                'match' => match ((int) $row->match_rank) {
                    0 => 'exact', 1 => 'prefix', default => 'partial'
                },
                'client' => $client,
                'person' => $person,
            ];
        });
    }

    private function people(string $table, int $roleOrder, ?string $text, ?string $dniHash): Builder
    {
        $query = DB::table($table)->select('id', 'search_name')->selectRaw('? as role_order', [$roleOrder]);
        $phone = Phone::normalize($text ?? '');
        if ($dniHash !== null) {
            return $query->selectRaw('0 as match_rank')->where('dni_hash', $dniHash);
        }
        if (preg_match('/^\d{8}$/', $phone)) {
            return $query->selectRaw('0 as match_rank')->where('phone', $phone);
        }
        if ($text === null || $text === '') {
            return $query->selectRaw('0 as match_rank');
        }
        $normalized = Client::normalizeSearchName($text);
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $normalized);

        return $query->selectRaw("CASE WHEN search_name = ? THEN 0 WHEN search_name LIKE ? ESCAPE '!' THEN 1 ELSE 2 END as match_rank", [$normalized, $escaped.'%'])
            ->whereRaw("search_name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
    }
}
