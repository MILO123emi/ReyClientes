<?php

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
        'gif' => UploadedFile::fake()->image('prueba.gif'),
    };
    $validator = Validator::make(['id_photo' => $file], ['id_photo' => IdPhotoRules::create()]);

    expect($validator->fails())->toBeTrue();
    $message = $validator->errors()->first('id_photo');
    expect($message)->toContain('foto de la cédula');
    expect($message)->toBe(match ($kind) {
        'missing' => 'El campo foto de la cédula es obligatorio.',
        'oversize' => 'El campo foto de la cédula no debe ser mayor que 8192 kilobytes.',
        'gif' => 'El campo foto de la cédula debe ser un archivo con formato: jpg, jpeg, png, webp.',
        default => 'El campo foto de la cédula debe ser una imagen.',
    });
})->with(['missing', 'pdf', 'text', 'oversize', 'gif']);

it('accepts valid photos within the upload size limit', function (string $extension) {
    Storage::fake('local');
    $file = UploadedFile::fake()->image('prueba.'.$extension)->size(8192);

    $validator = Validator::make(['id_photo' => $file], ['id_photo' => IdPhotoRules::create()]);

    expect($validator->passes())->toBeTrue();
})->with(['jpg', 'jpeg', 'png', 'webp']);

it('allows an unchanged photo on update', function () {
    Storage::fake('local');

    expect(Validator::make([], ['id_photo' => IdPhotoRules::update()])->passes())->toBeTrue();
    expect(Validator::make(['id_photo' => null], ['id_photo' => IdPhotoRules::update()])->passes())->toBeTrue();
});
