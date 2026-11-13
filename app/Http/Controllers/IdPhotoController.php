<?php

namespace App\Http\Controllers;

use App\Enums\PhotoOwner;
use App\Enums\PhotoVariant;
use App\Services\IdPhotoService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class IdPhotoController extends Controller
{
    public function show(PhotoOwner $type, int $id, PhotoVariant $variant, IdPhotoService $photos): Response
    {
        $model = $type->modelClass()::query()->findOrFail($id);
        Gate::authorize('view', $model);

        $bytes = $photos->read($model->{$variant->column()});
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
