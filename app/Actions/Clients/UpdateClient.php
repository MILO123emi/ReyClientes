<?php

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\PhotoOwner;
use App\Models\Client;
use App\Services\IdPhotoService;
use App\Support\Dni;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateClient
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(Client $client, array $data, ?UploadedFile $photo = null): Client
    {
        $paths = $photo ? $this->photos->store($photo, PhotoOwner::Client) : [];

        try {
            return DB::transaction(function () use ($client, $data, $paths): Client {
                $client = Client::query()->lockForUpdate()->findOrFail($client->id);
                $oldPaths = [$client->id_photo_path, $client->id_photo_thumb_path];
                $client->fill(Arr::only($data, ['full_name', 'dni', 'phone']));
                if ($client->authorizedPeople()->where('dni_hash', Dni::hash($client->dni))->exists()) {
                    throw ValidationException::withMessages(['dni' => 'Una persona autorizada de este cliente ya tiene esta identidad.']);
                }

                $client->full_name = Str::title($client->full_name);
                $fields = [];
                foreach (['full_name' => 'nombre', 'dni' => 'identidad', 'phone' => 'teléfono'] as $field => $label) {
                    if ($client->isDirty($field)) {
                        $fields[] = $label;
                    }
                }
                if ($paths !== []) {
                    $fields[] = 'foto';
                }
                if ($fields === []) {
                    return $client;
                }

                $client->forceFill([...$paths, 'updated_by' => auth()->id()])->save();
                $this->audit->handle(AuditAction::ClientUpdated, "Cliente {$client->full_name} ({$client->maskedDni()}) editado: ".implode(', ', $fields), $client);
                if ($paths !== []) {
                    $this->photos->deleteAfterCommit(...$oldPaths);
                }

                return $client;
            });
        } catch (Throwable $exception) {
            $this->photos->delete(...array_values($paths));
            throw $exception;
        }
    }
}
