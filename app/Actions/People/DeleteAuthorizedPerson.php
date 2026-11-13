<?php

namespace App\Actions\People;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Services\IdPhotoService;
use Illuminate\Support\Facades\DB;

class DeleteAuthorizedPerson
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    public function handle(AuthorizedPerson $person): void
    {
        DB::transaction(function () use ($person): void {
            $client = Client::query()->lockForUpdate()->findOrFail($person->client_id);
            $person = $client->authorizedPeople()->lockForUpdate()->findOrFail($person->id);
            $paths = [$person->id_photo_path, $person->id_photo_thumb_path];
            $description = "Persona {$person->full_name} ({$person->maskedDni()}) del cliente {$client->full_name}";
            $person->delete();
            $this->audit->handle(AuditAction::PersonDeleted, $description, $person);
            $this->photos->deleteAfterCommit(...$paths);
        });
    }
}
