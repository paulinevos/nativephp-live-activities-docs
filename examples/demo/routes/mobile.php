<?php

declare(strict_types=1);

use App\Native\ActivityDemo;
use Illuminate\Support\Facades\Route;

Route::native('/', ActivityDemo::class);
Route::native('/run/{run}', ActivityDemo::class);
