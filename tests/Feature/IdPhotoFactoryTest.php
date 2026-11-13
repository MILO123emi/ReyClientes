<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Services\IdPhotoService;
use Database\Factories\Concerns\MakesPlaceholderIdPhotos;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('creates readable encrypted placeholder photos with the factory state', function (string $model, string $directory) {
    Storage::fake('local');

    $record = $model::factory()->withIdPhoto()->create();

    foreach ([$record->id_photo_path, $record->id_photo_thumb_path] as $path) {
        expect($path)->toStartWith('id-photos/'.$directory.'/')->toEndWith('.enc');
        Storage::disk('local')->assertExists($path);
        expect(getimagesizefromstring(app(IdPhotoService::class)->read($path))['mime'])->toBe('image/jpeg');
    }
})->with(['client' => [Client::class, 'clients'], 'person' => [AuthorizedPerson::class, 'people']]);

it('keeps default factories from writing files', function (string $model) {
    Storage::fake('local');

    $record = $model::factory()->create();

    Storage::disk('local')->assertMissing([$record->id_photo_path, $record->id_photo_thumb_path]);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['client' => [Client::class], 'person' => [AuthorizedPerson::class]]);

it('generates a JPEG placeholder with the proportions of an identity card', function () {
    Storage::fake('local');
    $generator = new class
    {
        use MakesPlaceholderIdPhotos {
            placeholderIdPhoto as public;
        }
    };

    $jpeg = $generator->placeholderIdPhoto();

    expect(array_slice(getimagesizefromstring($jpeg), 0, 2))->toBe([1000, 630]);
    expect(getimagesizefromstring($jpeg)['mime'])->toBe('image/jpeg');
});

it('ships each fictional illustration as a private SVG and a 960 by 640 PNG', function (string $name) {
    $base = database_path('seeders/assets/id-photos/'.$name);

    expect(is_file($base.'.svg'))->toBeTrue();
    expect(is_file($base.'.png'))->toBeTrue();
    expect(array_slice(getimagesize($base.'.png'), 0, 2))->toBe([960, 640]);
    expect(getimagesize($base.'.png')['mime'])->toBe('image/png');
    expect(file_get_contents($base.'.svg'))->toContain('CÉDULA DE PRUEBA — SIN VALIDEZ');
})->with(['owner-1', 'owner-2', 'owner-3', 'owner-4', 'authorized-1', 'authorized-2', 'authorized-3', 'authorized-4']);

it('stores the matching role illustration through the encrypted photo service', function (string $model, array $color) {
    Storage::fake('local');

    $record = $model::factory()->withIdPhoto()->create();

    $full = app(IdPhotoService::class)->read($record->id_photo_path);
    $thumb = app(IdPhotoService::class)->read($record->id_photo_thumb_path);
    expect(array_slice(getimagesizefromstring($full), 0, 2))->toBe([960, 640]);
    expect(getimagesizefromstring($thumb)['mime'])->toBe('image/jpeg');
    expect(Storage::disk('local')->get($record->id_photo_path))->not->toBe($full);
    $image = imagecreatefromstring($full);
    $pixel = imagecolorsforindex($image, imagecolorat($image, 40, 40));
    imagedestroy($image);
    foreach (['red', 'green', 'blue'] as $channel) {
        expect($pixel[$channel])->toBeBetween($color[$channel] - 5, $color[$channel] + 5);
    }
})->with([
    'owner blue' => [Client::class, ['red' => 37, 'green' => 82, 'blue' => 195]],
    'authorized gold' => [AuthorizedPerson::class, ['red' => 255, 'green' => 201, 'blue' => 60]],
]);

it('falls back to the previous placeholder when development assets are missing', function (string $model) {
    Storage::fake('local');
    $originalPath = database_path();
    $this->app->useDatabasePath(storage_path('framework/testing/missing-id-assets'));

    try {
        $record = $model::factory()->withIdPhoto()->create();
        $full = app(IdPhotoService::class)->read($record->id_photo_path);

        expect(array_slice(getimagesizefromstring($full), 0, 2))->toBe([1000, 630]);
        expect(getimagesizefromstring($full)['mime'])->toBe('image/jpeg');
    } finally {
        $this->app->useDatabasePath($originalPath);
    }
})->with(['client' => [Client::class], 'authorized' => [AuthorizedPerson::class]]);

it('keeps every illustration distinct and outside the public directory', function () {
    $assets = File::allFiles(database_path('seeders/assets/id-photos'));
    $publicFiles = File::allFiles(public_path());
    $assetHashes = array_map(fn ($file) => hash_file('sha256', $file->getPathname()), $assets);
    $publicHashes = array_map(fn ($file) => hash_file('sha256', $file->getPathname()), $publicFiles);

    expect($assets)->toHaveCount(16);
    expect(array_unique($assetHashes))->toHaveCount(16);
    expect(array_intersect($assetHashes, $publicHashes))->toBe([]);
});
