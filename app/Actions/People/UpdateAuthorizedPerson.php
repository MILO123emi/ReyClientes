<?php

namespace App\Actions\People;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\PhotoOwner;
use App\Enums\Relationship;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Services\IdPhotoService;
use App\Support\Dni;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateAuthorizedPerson
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(AuthorizedPerson $person, array $data, ?UploadedFile $photo = null): AuthorizedPerson
    {
        $paths = $photo ? $this->photos->store($photo, PhotoOwner::Person) : [];

        try {
            return DB::transaction(function () use ($person, $data, $paths): AuthorizedPerson {
                $client = Client::query()->lockForUpdate()->findOrFail($person->client_id);
                $person = $client->authorizedPeople()->lockForUpdate()->findOrFail($person->id);
                $oldPaths = [$person->id_photo_path, $person->id_photo_thumb_path];
                $person->fill(Arr::only($data, ['full_name', 'dni', 'phone', 'relationship', 'relationship_other']));
                if ($client->dni_hash === Dni::hash($person->dni)) {
                    throw ValidationException::withMessages(['dni' => 'La persona autorizada no puede tener la misma identidad que el titular.']);
                }

                $person->full_name = Str::title($person->full_name);
                if ($person->relationship !== Relationship::Other) {
                    $person->relationship_other = null;
                }

                $fields = [];
                foreach (['full_name' => 'nombre', 'dni' => 'identidad', 'phone' => 'teléfono', 'relationship' => 'parentesco', 'relationship_other' => 'detalle del parentesco'] as $field => $label) {
                    if ($person->isDirty($field)) {
                        $fields[] = $label;
                    }
                }
                if ($paths !== []) {
                    $fields[] = 'foto';
                }
                if ($fields === []) {
                    return $person;
                }

                $person->forceFill([...$paths, 'updated_by' => auth()->id()])->save();
                $this->audit->handle(AuditAction::PersonUpdated, "Persona {$person->full_name} ({$person->maskedDni()}) del cliente {$client->full_name} editada: ".implode(', ', $fields), $person);
                if ($paths !== []) {
                    $this->photos->deleteAfterCommit(...$oldPaths);
                }

                return $person;
            });
        } catch (Throwable $exception) {
            $this->photos->delete(...array_values($paths));
            throw $exception;
        }
    }
}
