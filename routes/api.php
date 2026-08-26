<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TestController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\ProfilePhotoController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\HomeController;
use App\Http\Middleware\ResolveApplication;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\RateUsController;
use App\Http\Controllers\Api\V1\LegalController;
use App\Http\Controllers\Api\V1\WalletController;
use App\Http\Controllers\Api\V1\SuccessStoryController;
use App\Http\Controllers\Api\V1\DeleteProfileRequestController;
use App\Http\Controllers\Api\V1\ProfileLikeController;
use App\Http\Controllers\Api\V1\ShortlistedController;
use App\Http\Controllers\Api\V1\SentInterestController;

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

        Route::prefix('auth')->group(function () {

            Route::post('/register', [
                AuthController::class,
                'register'
            ]);

            Route::post(
                '/register/step-2/{memberId}',
                [AuthController::class, 'registrationStep2']
            )->name('register.step2');

            Route::post(
                '/register/step-3/{memberId}',
                [AuthController::class, 'registrationStep3']
            )->name('register.step3');

            Route::post(
                '/register/step-4/{memberId}',
                [AuthController::class, 'registrationStep4']
            )->name('register.step4');

            Route::post(
                '/register/step-5/{memberId}',
                [AuthController::class, 'registrationStep5']
            )->name('register.step5');

            Route::post('/login', [
                AuthController::class,
                'login'
            ]);
        });

        Route::middleware(ResolveApplication::class, 'auth:sanctum')
            ->group(function () {

                Route::get('/profile', [
                    ProfileController::class,
                    'show'
                ]);

                Route::put('/change-password', [
                    AuthController::class,
                    'changePassword'
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

                //Search
                Route::get('/search/quick', [
                    SearchController::class,
                    'quickSearch'
                ]);

                Route::get('/search/profile/{profileId}', [
                    SearchController::class,
                    'searchByProfileId'
                ]);

                Route::get('/search/advanced', [
                    SearchController::class,
                    'advancedSearch'
                ]);

                //home section
                Route::get('/home', [
                    HomeController::class,
                    'index'
                ]);

                //Membership
                Route::get('/memberships', [
                    MembershipController::class,
                    'index'
                ]);

                Route::get('/memberships/{membershipTypeId}/plans', [
                    MembershipController::class,
                    'plans'
                ]);

                //Rate us
                Route::post('/rate-us', [
                    RateUsController::class,
                    'store'
                ]);

                //wallet
                Route::get('/wallet', [
                    WalletController::class,
                    'index'
                ]);

                Route::get('/wallet/transactions', [
                    WalletController::class,
                    'transactions'
                ]);

                Route::post(
                    '/wallet/add-money/order',
                    [WalletController::class, 'createAddMoneyOrder']
                );

                Route::post(
                    '/wallet/add-money/verify',
                    [WalletController::class, 'verifyAddMoney']
                );

                /*
                |--------------------------------------------------------------------------
                | Success Stories
                |--------------------------------------------------------------------------
                */

                Route::get(
                    '/success-stories',
                    [SuccessStoryController::class, 'index']
                );

                Route::get(
                    '/success-stories',
                    [SuccessStoryController::class, 'myStories']
                );

                Route::post(
                    '/success-stories',
                    [SuccessStoryController::class, 'store']
                );

                Route::get(
                    '/success-stories/{id}',
                    [SuccessStoryController::class, 'show']
                );

                Route::put(
                    '/success-stories/{id}',
                    [SuccessStoryController::class, 'update']
                );

                Route::delete(
                    '/success-stories/{id}',
                    [SuccessStoryController::class, 'destroy']
                );

                //delete profile request
                Route::post(
                    '/profile/delete-request',
                    [DeleteProfileRequestController::class, 'store']
                );

                Route::get(
                    '/profile/delete-request',
                    [DeleteProfileRequestController::class, 'index']
                );

                //profile likes
                Route::get(
                    '/profile-likes',
                    [ProfileLikeController::class, 'index']
                );

                Route::get(
                    '/profile-likes/{memberId}',
                    [ProfileLikeController::class, 'show']
                );

                Route::post(
                    '/profile-likes/{memberId}',
                    [ProfileLikeController::class, 'store']
                );

                Route::delete(
                    '/profile-likes/{memberId}',
                    [ProfileLikeController::class, 'destroy']
                );

                //shortlisted profiles
                Route::get(
                    '/shortlisted',
                    [ShortlistedController::class, 'index']
                );

                Route::get(
                    '/shortlisted/{profileId}',
                    [ShortlistedController::class, 'show']
                );

                Route::post(
                    '/shortlisted/{profileId}',
                    [ShortlistedController::class, 'store']
                );

                Route::delete(
                    '/shortlisted/{profileId}',
                    [ShortlistedController::class, 'destroy']
                );

                //sent interest

                Route::post(
                    '/interests/{profileId}',
                    [SentInterestController::class, 'store']
                );

                Route::get(
                    '/interests/sent',
                    [SentInterestController::class, 'index']
                );

                Route::get(
                    '/interests/received',
                    [SentInterestController::class, 'received']
                );

                Route::put(
                    '/interests/{id}/accept',
                    [SentInterestController::class, 'accept']
                );

                Route::put(
                    '/interests/{id}/reject',
                    [SentInterestController::class, 'reject']
                );

                Route::delete(
                    '/interests/{id}/cancel',
                    [SentInterestController::class, 'cancel']
                );

                Route::get(
                    '/interests/{profileId}',
                    [SentInterestController::class, 'show']
                );
            });

        Route::get('/privacy-policy', [
            LegalController::class,
            'privacyPolicy'
        ]);

        Route::get('/refund-cancellation', [
            LegalController::class,
            'refundCancellation'
        ]);

        Route::get('/terms-and-conditions', [
            LegalController::class,
            'termsAndConditions'
        ]);

        Route::get('/about-us', [
            LegalController::class,
            'aboutUs'
        ]);

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
