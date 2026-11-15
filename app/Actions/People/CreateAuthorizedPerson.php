<?php

namespace App\Actions\People;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\PhotoOwner;
use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Services\IdPhotoService;
use App\Support\Dni;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateAuthorizedPerson
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Client $client, array $data, UploadedFile $photo): AuthorizedPerson
    {
        $paths = $this->photos->store($photo, PhotoOwner::Person);

        try {
            return DB::transaction(function () use ($client, $data, $paths): AuthorizedPerson {
                $client = Client::query()->lockForUpdate()->findOrFail($client->id);
                $person = $client->authorizedPeople()->make(Arr::only($data, ['full_name', 'dni', 'phone', 'relationship', 'relationship_other']));
                if ($client->dni_hash === Dni::hash($person->dni)) {
                    throw ValidationException::withMessages(['dni' => 'La persona autorizada no puede tener la misma identidad que el titular.']);
                }

                $person->full_name = Str::title($person->full_name);
                $person->forceFill([...$paths, 'created_by' => auth()->id(), 'updated_by' => auth()->id()])->save();
                $this->audit->handle(AuditAction::PersonCreated, "Persona {$person->full_name} ({$person->maskedDni()}) del cliente {$client->full_name}", $person);

                return $person;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical submissions raced past validation: answer like the validator would.
            $this->photos->delete(...array_values($paths));

            throw ValidationException::withMessages(['dni' => 'Esta persona ya está autorizada por este cliente.']);
        } catch (Throwable $exception) {
            $this->photos->delete(...array_values($paths));
            throw $exception;
        }
    }
}
