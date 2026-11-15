<?php

namespace App\Actions\Clients;

use App\Actions\Audit\RecordAudit;
use App\Enums\AuditAction;
use App\Enums\PhotoOwner;
use App\Models\Client;
use App\Services\IdPhotoService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateClient
{
    public function __construct(private IdPhotoService $photos, private RecordAudit $audit) {}

    /** @param array<string, mixed> $data */
    public function handle(array $data, UploadedFile $photo): Client
    {
        $paths = $this->photos->store($photo, PhotoOwner::Client);

        try {
            return DB::transaction(function () use ($data, $paths): Client {
                $client = new Client(Arr::only($data, ['full_name', 'dni', 'phone']));
                $client->full_name = Str::title($client->full_name);
                $client->forceFill([...$paths, 'created_by' => auth()->id(), 'updated_by' => auth()->id()])->save();
                $this->audit->handle(AuditAction::ClientCreated, "Cliente {$client->full_name} ({$client->maskedDni()})", $client);

                return $client;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical submissions raced past validation: answer like the validator would.
            $this->photos->delete(...array_values($paths));

            throw ValidationException::withMessages(['dni' => 'Ya existe un cliente con esta identidad.']);
        } catch (Throwable $exception) {
            $this->photos->delete(...array_values($paths));
            throw $exception;
        }
    }
}
