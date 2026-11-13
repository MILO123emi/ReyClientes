<?php

use App\Models\AuthorizedPerson;
use App\Models\Client;
use App\Services\IdPhotoService;
use Database\Factories\Concerns\MakesPlaceholderIdPhotos;
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
