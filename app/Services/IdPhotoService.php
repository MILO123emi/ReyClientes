<?php

namespace App\Services;

use App\Enums\PhotoOwner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use RuntimeException;

class IdPhotoService
{
    public const MAX_PIXELS = 50_000_000;

    /** @return array{id_photo_path: string, id_photo_thumb_path: string} */
    public function store(UploadedFile|string $image, PhotoOwner $owner): array
    {
        $size = $image instanceof UploadedFile
            ? @getimagesize($image->getPathname())
            : @getimagesizefromstring($image);

        if ($size === false) {
            throw ValidationException::withMessages(['id_photo' => 'No se pudo leer la imagen.']);
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw ValidationException::withMessages(['id_photo' => 'La imagen es demasiado grande (máximo 50 megapíxeles).']);
        }

        $previousMemoryLimit = (string) ini_get('memory_limit');
        $raiseMemoryLimit = $previousMemoryLimit !== '-1' && ini_parse_quantity($previousMemoryLimit) < 512 * 1024 * 1024;

        if ($raiseMemoryLimit) {
            ini_set('memory_limit', '512M');
        }

        try {
            $manager = new ImageManager(Driver::class, autoOrientation: true, strip: true);

            try {
                $processed = $manager->read($image instanceof UploadedFile ? $image->getPathname() : $image);
            } catch (DecoderException) {
                throw ValidationException::withMessages(['id_photo' => 'No se pudo leer la imagen.']);
            }

            $full = (string) $processed->scaleDown(1600, 1600)->toJpeg(quality: 82);
            $thumb = (string) $processed->scaleDown(320, 320)->toJpeg(quality: 75);
        } finally {
            unset($processed);

            if ($raiseMemoryLimit) {
                ini_set('memory_limit', $previousMemoryLimit);
            }
        }

        $uuid = Str::uuid()->toString();
        $directory = 'id-photos/'.$owner->directory();
        $paths = [
            'id_photo_path' => "{$directory}/{$uuid}.enc",
            'id_photo_thumb_path' => "{$directory}/{$uuid}_thumb.enc",
        ];
        $disk = Storage::disk('local');

        if (! $disk->put($paths['id_photo_path'], Crypt::encryptString(base64_encode($full)))) {
            throw new RuntimeException('No se pudo guardar la foto.');
        }

        if (! $disk->put($paths['id_photo_thumb_path'], Crypt::encryptString(base64_encode($thumb)))) {
            $disk->delete($paths['id_photo_path']);

            throw new RuntimeException('No se pudo guardar la foto.');
        }

        return $paths;
    }

    public function read(string $path): ?string
    {
        $contents = Storage::disk('local')->get($path);

        if ($contents === null) {
            return null;
        }

        $bytes = base64_decode(Crypt::decryptString($contents), true);

        if ($bytes === false) {
            throw new RuntimeException('No se pudo leer la foto.');
        }

        return $bytes;
    }

    public function delete(string ...$paths): void
    {
        $paths = array_values(array_filter($paths, fn (string $path): bool => $path !== ''));

        if ($paths !== []) {
            Storage::disk('local')->delete($paths);
        }
    }

    public function deleteAfterCommit(string ...$paths): void
    {
        DB::afterCommit(fn () => $this->delete(...$paths));
    }
}
