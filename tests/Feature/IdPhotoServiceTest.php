<?php

use App\Enums\PhotoOwner;
use App\Services\IdPhotoService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

it('stores two encrypted UUID files in the owners directory', function (PhotoOwner $owner, string $directory) {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    $memoryLimit = ini_get('memory_limit');

    $paths = $photos->store(UploadedFile::fake()->image('cedula-de-prueba.jpg', 1000, 630), $owner);

    expect($paths)->toHaveCount(2);
    expect($paths['id_photo_path'])->toMatch('~^id-photos/'.$directory.'/[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}\.enc$~');
    expect($paths['id_photo_thumb_path'])->toBe(substr($paths['id_photo_path'], 0, -4).'_thumb.enc');
    expect(Storage::disk('local')->allFiles())->toHaveCount(2);
    foreach ($paths as $path) {
        $raw = Storage::disk('local')->get($path);
        expect($raw)->not->toStartWith("\xff\xd8");
        expect(base64_decode(Crypt::decryptString($raw), true))->toBe($photos->read($path));
        expect(getimagesizefromstring($photos->read($path))['mime'])->toBe('image/jpeg');
    }
    expect(ini_get('memory_limit'))->toBe($memoryLimit);
})->with(['client' => [PhotoOwner::Client, 'clients'], 'person' => [PhotoOwner::Person, 'people']]);

it('applies EXIF orientation and removes metadata from both outputs', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    $input = jpegWithExif(400, 200);
    expect($input)->toContain("Exif\0\0", 'ReyPass Test');

    $paths = $photos->store($input, PhotoOwner::Client);

    $full = $photos->read($paths['id_photo_path']);
    expect(array_slice(getimagesizefromstring($full), 0, 2))->toBe([200, 400]);
    foreach ($paths as $path) {
        expect($photos->read($path))->not->toContain("Exif\0\0", 'ReyPass Test');
    }
});

it('scales down full photos and thumbnails while preserving their proportions', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);

    $paths = $photos->store(jpegWithExif(3000, 2000, 1), PhotoOwner::Client);

    expect(array_slice(getimagesizefromstring($photos->read($paths['id_photo_path'])), 0, 2))->toBe([1600, 1067]);
    expect(array_slice(getimagesizefromstring($photos->read($paths['id_photo_thumb_path'])), 0, 2))->toBe([320, 213]);
});

it('does not enlarge smaller images', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);

    $paths = $photos->store(jpegWithExif(800, 600, 1), PhotoOwner::Client);

    expect(array_slice(getimagesizefromstring($photos->read($paths['id_photo_path'])), 0, 2))->toBe([800, 600]);
});

it('converts PNG and WebP inputs to JPEG', function (string $extension) {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);

    $paths = $photos->store(UploadedFile::fake()->image('prueba.'.$extension, 600, 400), PhotoOwner::Client);

    foreach ($paths as $path) {
        expect(getimagesizefromstring($photos->read($path))['mime'])->toBe('image/jpeg');
    }
})->with(['png', 'webp']);

it('rejects unreadable images without creating files', function (string $kind) {
    Storage::fake('local');
    $input = match ($kind) {
        'text' => 'Esto no es una imagen.',
        'corrupt-jpeg' => "\xff\xd8\xff\xe0corrupto",
        'header-only-png' => pngHeaderOnly(100, 100),
    };
    $memoryLimit = ini_get('memory_limit');

    expect(fn () => app(IdPhotoService::class)->store($input, PhotoOwner::Client))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['id_photo' => ['No se pudo leer la imagen.']]);
        });
    expect(Storage::disk('local')->allFiles())->toBe([]);
    expect(ini_get('memory_limit'))->toBe($memoryLimit);
})->with(['text', 'corrupt-jpeg', 'header-only-png']);

it('rejects dimensions above fifty megapixels before decoding', function () {
    Storage::fake('local');

    expect(fn () => app(IdPhotoService::class)->store(pngHeaderOnly(10000, 6000), PhotoOwner::Client))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['id_photo' => ['La imagen es demasiado grande (máximo 50 megapíxeles).']]);
        });
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('treats exactly fifty megapixels as within the dimension limit', function () {
    Storage::fake('local');

    expect(fn () => app(IdPhotoService::class)->store(pngHeaderOnly(10000, 5000), PhotoOwner::Client))
        ->toThrow(function (ValidationException $exception) {
            expect($exception->errors())->toBe(['id_photo' => ['No se pudo leer la imagen.']]);
        });
});

it('returns null for absent files and propagates decryption errors', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    expect($photos->read('id-photos/missing.enc'))->toBeNull();
    Storage::disk('local')->put('id-photos/corrupt.enc', 'contenido corrupto');

    expect(fn () => $photos->read('id-photos/corrupt.enc'))->toThrow(DecryptException::class);
});

it('rejects decrypted content that is not valid base64', function () {
    Storage::fake('local');
    Storage::disk('local')->put('id-photos/invalid.enc', Crypt::encryptString('%%%'));

    expect(fn () => app(IdPhotoService::class)->read('id-photos/invalid.enc'))
        ->toThrow(RuntimeException::class, 'No se pudo leer la foto.');
});

it('cleans up when writing a photo fails', function (bool $failThumbnail) {
    $disk = Storage::fake('local');
    $failingDisk = Mockery::mock(FilesystemAdapter::class);
    $failingDisk->shouldReceive('put')->times($failThumbnail ? 2 : 1)
        ->andReturnUsing(fn (string $path, string $contents): bool => $failThumbnail && ! str_ends_with($path, '_thumb.enc')
            ? $disk->put($path, $contents)
            : false);
    if ($failThumbnail) {
        $failingDisk->shouldReceive('delete')->once()->andReturnUsing(fn (string $path): bool => $disk->delete($path));
    }
    Storage::shouldReceive('disk')->with('local')->andReturn($failingDisk);

    expect(fn () => app(IdPhotoService::class)->store(jpegWithExif(100, 100), PhotoOwner::Client))
        ->toThrow(RuntimeException::class, 'No se pudo guardar la foto.');
    expect($disk->allFiles())->toBe([]);
})->with(['full' => [false], 'thumbnail' => [true]]);

it('deletes both files and ignores empty paths', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    $paths = $photos->store(jpegWithExif(100, 100), PhotoOwner::Client);

    $photos->delete('', ...array_values($paths));

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('preserves files when a transaction is rolled back', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    $paths = $photos->store(jpegWithExif(100, 100), PhotoOwner::Client);

    expect(fn () => DB::transaction(function () use ($photos, $paths) {
        $photos->deleteAfterCommit(...array_values($paths));
        throw new RuntimeException('Revertir transacción de prueba.');
    }))->toThrow(RuntimeException::class, 'Revertir transacción de prueba.');

    Storage::disk('local')->assertExists(array_values($paths));
});

it('deletes files only after a transaction commits', function () {
    Storage::fake('local');
    $photos = app(IdPhotoService::class);
    $paths = $photos->store(jpegWithExif(100, 100), PhotoOwner::Client);

    DB::transaction(function () use ($photos, $paths) {
        $photos->deleteAfterCommit(...array_values($paths));
        Storage::disk('local')->assertExists(array_values($paths));
    });

    Storage::disk('local')->assertMissing(array_values($paths));
});
