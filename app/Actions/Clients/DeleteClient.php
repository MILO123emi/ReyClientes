<?php

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\Client;
use App\Services\IdPhotoService;
use Illuminate\Support\Facades\DB;

class DeleteClient
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    public function handle(Client $client): void
    {
        DB::transaction(function () use ($client): void {
            $client = Client::query()->lockForUpdate()->findOrFail($client->id);
            $people = $client->authorizedPeople()->get();
            $paths = [$client->id_photo_path, $client->id_photo_thumb_path];
            foreach ($people as $person) {
                array_push($paths, $person->id_photo_path, $person->id_photo_thumb_path);
                $person->delete();
            }

            $count = $people->count();
            $description = "Cliente {$client->full_name} ({$client->maskedDni()}) eliminado con {$count} personas autorizadas";
            $client->delete();
            $this->audit->handle(AuditAction::ClientDeleted, $description, $client);
            $this->photos->deleteAfterCommit(...$paths);
        });
    }
}
