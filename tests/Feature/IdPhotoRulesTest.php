<?php

use App\Enums\PhotoOwner;
use App\Services\IdPhotoService;
use App\Support\IdPhotoRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

it('rejects missing or invalid photos with Spanish messages', function (string $kind) {
    Storage::fake('local');
    $file = match ($kind) {
        'missing' => null,
        'pdf' => UploadedFile::fake()->create('prueba.pdf', 10, 'application/pdf'),
        'text' => UploadedFile::fake()->create('prueba.txt', 10, 'text/plain'),
        'oversize' => UploadedFile::fake()->image('prueba.jpg')->size(8193),
        'svg' => UploadedFile::fake()->createWithContent('prueba.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'),
    };
    $validator = Validator::make(['id_photo' => $file], ['id_photo' => IdPhotoRules::create()]);

    expect($validator->fails())->toBeTrue();
    $message = $validator->errors()->first('id_photo');
    expect($message)->toContain('foto de la cédula');
    expect($message)->toBe(match ($kind) {
        'missing' => 'El campo foto de la cédula es obligatorio.',
        'oversize' => 'El campo foto de la cédula no debe ser mayor que 8192 kilobytes.',
        default => 'La foto de la cédula debe ser una imagen JPG, PNG, WebP, GIF, BMP o AVIF.',
    });
})->with(['missing', 'pdf', 'text', 'oversize', 'svg']);

it('accepts valid photos within the upload size limit', function (string $extension) {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('prueba.'.$extension)->size(8192);

    $validator = Validator::make(['id_photo' => $file], ['id_photo' => IdPhotoRules::create()]);

    expect($validator->passes())->toBeTrue();
})->with(['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp']);

it('allows an unchanged photo on update', function () {
    Storage::fake('local');

    expect(Validator::make([], ['id_photo' => IdPhotoRules::update()])->passes())->toBeTrue();
    expect(Validator::make(['id_photo' => null], ['id_photo' => IdPhotoRules::update()])->passes())->toBeTrue();
});

it('stores every accepted format as an encrypted JPEG', function (string $extension) {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('prueba.'.$extension, 600, 400);

    $paths = app(IdPhotoService::class)->store($file, PhotoOwner::Client);

    $full = app(IdPhotoService::class)->read($paths['id_photo_path']);
    expect(getimagesizefromstring($full)['mime'])->toBe('image/jpeg');
})->with(['jpg', 'png', 'webp', 'gif', 'bmp']);

it('accepts and stores an AVIF photo', function () {
    Storage::fake('local');
    $canvas = imagecreatetruecolor(300, 200);
    ob_start();
    imageavif($canvas);
    $file = UploadedFile::fake()->createWithContent('prueba.avif', (string) ob_get_clean());

    expect(Validator::make(['id_photo' => $file], ['id_photo' => IdPhotoRules::create()])->passes())->toBeTrue();
    $paths = app(IdPhotoService::class)->store($file, PhotoOwner::Client);
    expect(getimagesizefromstring(app(IdPhotoService::class)->read($paths['id_photo_path']))['mime'])->toBe('image/jpeg');
})->skip(fn () => ! function_exists('imageavif'), 'GD sin AVIF');
