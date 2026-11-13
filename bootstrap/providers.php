<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\RecoveryServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    RecoveryServiceProvider::class,
];
