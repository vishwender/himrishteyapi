<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\Member;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;

class AuthController extends Controller
{
    /**
     * Login member.
     *
     * Supported login identifiers:
     * - profile_id
     * - email
     * - mobile_number
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $login = trim($request->input('login'));
        $password = $request->input('password');

        /*
        |--------------------------------------------------------------------------
        | Determine login type
        |--------------------------------------------------------------------------
        */

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {

            $field = 'email';
        } elseif (preg_match('/^[0-9+\-\s()]+$/', $login)) {

            $field = 'mobile_number';
        } else {

            $field = 'profile_id';
        }

        /*
        |--------------------------------------------------------------------------
        | Find member
        |--------------------------------------------------------------------------
        |
        | Member model uses the "application" connection, which was
        | established by ResolveApplication middleware.
        |
        */

        $members = Member::query()
            ->where($field, $login)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | No account found
        |--------------------------------------------------------------------------
        */

        if ($members->isEmpty()) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate account protection
        |--------------------------------------------------------------------------
        |
        | We know duplicates exist in the legacy database.
        | Until they are cleaned, do not randomly select an account.
        |
        */

        if ($members->count() > 1) {

            return response()->json([
                'success' => false,
                'message' => 'Multiple accounts found. Please use your profile ID.',
                'login_type' => $field,
            ], 409);
        }

        /*
        |--------------------------------------------------------------------------
        | Get member
        |--------------------------------------------------------------------------
        */

        $member = $members->first();

        /*
        |--------------------------------------------------------------------------
        | Verify password
        |--------------------------------------------------------------------------
        |
        | TEMPORARY:
        | Existing database passwords are currently plaintext.
        |
        | We will migrate these to secure hashes later.
        |
        */

        if ($member->password !== $password) {

            return response()->json([
                'success' => false,
                'message' => 'Invalid login credentials.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Get resolved application
        |--------------------------------------------------------------------------
        */

        $application = $request->attributes->get('application');

        if (!$application) {

            return response()->json([
                'success' => false,
                'message' => 'Application context is missing.',
            ], 500);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Sanctum token
        |--------------------------------------------------------------------------
        */

        $plainTextToken = Str::random(40);

        $hashedToken = hash('sha256', $plainTextToken);

        $personalAccessToken = new PersonalAccessToken();

        $personalAccessToken->setConnection('mariadb');

        $personalAccessToken->name = 'mobile-app';
        $personalAccessToken->token = $hashedToken;
        $personalAccessToken->abilities = ['*'];
        $personalAccessToken->tokenable_id = $member->id;
        $personalAccessToken->tokenable_type = $member->getMorphClass();
        $personalAccessToken->application_id = $application->id;

        $personalAccessToken->save();

        $accessToken = $plainTextToken;

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',

            'data' => [

                'token' => $accessToken,

                'token_type' => 'Bearer',

                'application' => [
                    'id' => $application->id,
                    'name' => $application->name,
                    'code' => $application->code,
                ],

                'member' => [
                    'id' => $member->id,
                    'profile_id' => $member->profile_id,
                    'full_name' => $member->full_name,
                    'email' => $member->email,
                    'mobile_number' => $member->mobile_number,
                ],
            ],
        ]);
    }

    /**
     * Create a Sanctum API token in the central database.
     */
    public function createToken(
        string $name,
        array $abilities = ['*'],
        ?\DateTimeInterface $expiresAt = null
    ): NewAccessToken {
        $token = new PersonalAccessToken();

        $token->setConnection('mariadb');

        $token->forceFill([
            'name' => $name,
            'token' => hash('sha256', $plainTextToken = Str::random(40)),
            'abilities' => $abilities,
            'expires_at' => $expiresAt,
        ]);

        $token->tokenable()->associate($this);

        $token->save();

        return new NewAccessToken(
            $token,
            $token->getKey() . '|' . $plainTextToken
        );
    }
}
