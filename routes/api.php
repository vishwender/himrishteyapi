<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ProfilePhotoController;

Route::prefix('v1')
    ->middleware('application')
    ->group(function () {

        Route::get('/test-member-schema', function () {

            return response()->json([
                'database' => DB::connection('application')->getDatabaseName(),

                'columns' => DB::connection('application')
                    ->getSchemaBuilder()
                    ->getColumnListing('members'),

                'member_20' => \App\Models\Member::find(20)?->toArray(),
            ]);
        });
        /*
        |--------------------------------------------------------------------------
        | Authentication
        |--------------------------------------------------------------------------
        */

        Route::post('/auth/login', [
            AuthController::class,
            'login'
        ]);

        Route::middleware('auth:sanctum')
            ->group(function () {

                Route::get('/profile', [
                    ProfileController::class,
                    'show'
                ]);

                Route::put('/profile/basic', [
                    ProfileController::class,
                    'updateBasic'
                ]);

                Route::put('/profile/personal', [
                    ProfileController::class,
                    'updatePersonal'
                ]);

                Route::put('/profile/religion', [
                    ProfileController::class,
                    'updateReligion'
                ]);

                Route::put('/profile/education-career', [
                    ProfileController::class,
                    'updateEducationCareer'
                ]);

                Route::put('/profile/family', [
                    ProfileController::class,
                    'updateFamily'
                ]);

                Route::put('/profile/lifestyle', [
                    ProfileController::class,
                    'updateLifestyle'
                ]);

                Route::put('/profile/partner-preferences', [
                    ProfileController::class,
                    'updatePartnerPreferences'
                ]);

                Route::put('/profile/location', [
                    ProfileController::class,
                    'updateLocation'
                ]);

                Route::post(
                    '/profile/photos/gallery',
                    [ProfilePhotoController::class, 'uploadGalleryPhoto']
                );
            });

        /*
        |--------------------------------------------------------------------------
        | Test
        |--------------------------------------------------------------------------
        */

        Route::get('/test', [
            TestController::class,
            'index'
        ]);
    });
