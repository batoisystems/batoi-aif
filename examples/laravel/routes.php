<?php

declare(strict_types=1);

use App\Http\Controllers\AifController;
use Illuminate\Support\Facades\Route;

Route::post('/aif/infer', [AifController::class, 'infer'])->middleware('auth');

