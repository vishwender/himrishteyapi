<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Models\Member;
use App\Models\PersonalAccessToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Laravel\Sanctum\NewAccessToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Register a new member - Step 1.
     *
     * Step 1 collects:
     * - Profile created for
     * - Full name
     * - Email
     * - Mobile number
     * - Gender
     * - Date of birth
     * - Password
     */
    public function register(Request $request): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Validate Step 1
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'profile_created_for' => [
                'required',
                'string',
                'max:100',
            ],

            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'mobile_number' => [
                'required',
                'string',
                'max:30',
            ],

            'gender' => [
                'required',
                'string',
                'max:50',
            ],

            'birth_date_time' => [
                'required',
                'date',
            ],

            'password' => [
                'required',
                'string',
                'min:6',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Get current application
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
    | Determine profile ID prefix
    |--------------------------------------------------------------------------
    */

        $prefix = match ($application->code) {

            'himrishteymain_base',
            'himrishtey_main',
            'himrishtey' => 'HIM',

            'devbhoomi',
            'himrishteymain_devbhoomi' => 'DR',

            'gallpakki',
            'himrishteymain_gallpakki' => 'PB',

            'dogririshtey',
            'himrishteymain_dogririshtey' => 'JR',

            default => null,
        };

        if (!$prefix) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to determine profile ID prefix for this application.',
            ], 500);
        }

        /*
    |--------------------------------------------------------------------------
    | Check duplicate email
    |--------------------------------------------------------------------------
    */

        if (
            Member::query()
            ->where('email', $validated['email'])
            ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'An account with this email already exists.',
                'field' => 'email',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Check duplicate mobile
    |--------------------------------------------------------------------------
    */

        if (
            Member::query()
            ->where('mobile_number', $validated['mobile_number'])
            ->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'An account with this mobile number already exists.',
                'field' => 'mobile_number',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Create Member
    |--------------------------------------------------------------------------
    */

        $member = DB::connection('application')->transaction(
            function () use ($validated, $prefix) {

                $member = new Member();

                /*
        |--------------------------------------------------------------------------
        | Step 1 data
        |--------------------------------------------------------------------------
        */

                $member->profile_created_for =
                    $validated['profile_created_for'];

                $member->full_name =
                    $validated['full_name'];

                $member->email =
                    $validated['email'];

                $member->mobile_number =
                    $validated['mobile_number'];

                $member->gender =
                    $validated['gender'];

                $member->birth_date_time =
                    $validated['birth_date_time'];

                /*
        |--------------------------------------------------------------------------
        | Password
        |--------------------------------------------------------------------------
        */

                $member->password =
                    $validated['password'];

                /*
        |--------------------------------------------------------------------------
        | Registration information
        |--------------------------------------------------------------------------
        */

                $member->registration_date =
                    now()->format('Y-m-d H:i:s');

                $member->register_through =
                    'API';

                /*
        |--------------------------------------------------------------------------
        | IMPORTANT
        |--------------------------------------------------------------------------
        |
        | profile_id cannot be generated using $member->id until after
        | the first INSERT.
        |
        | Therefore we temporarily use a database-safe placeholder.
        |
        | After INSERT, we replace it with:
        |
        | HIM28170
        | PB367
        | DR8431
        | JR6797
        |
        */

                $member->profile_id = '';

                /*
        |--------------------------------------------------------------------------
        | Plan information
        |--------------------------------------------------------------------------
        |
        | Legacy databases use plan_id = 0 for users without a plan.
        |
        */

                $member->plan_id = '0';

                /*
        |--------------------------------------------------------------------------
        | Plan activation
        |--------------------------------------------------------------------------
        */

                $member->plan_activation_date = null;

                /*
        |--------------------------------------------------------------------------
        | Basic defaults
        |--------------------------------------------------------------------------
        */

                $member->alternate_number = '';
                $member->whatsapp_number = '';

                $member->height = '';
                $member->blood_group = '';
                $member->health_info = '';
                $member->birth_place = '';

                /*
        |--------------------------------------------------------------------------
        | Religion
        |--------------------------------------------------------------------------
        */

                $member->religion = '';
                $member->mother_tongue = '';
                $member->cast = '';
                $member->sub_cast = '';
                $member->gotra = '';
                $member->manglik = '';

                /*
        |--------------------------------------------------------------------------
        | Marital
        |--------------------------------------------------------------------------
        */

                $member->marital_status = '';
                $member->no_of_child = '';

                /*
        |--------------------------------------------------------------------------
        | Education
        |--------------------------------------------------------------------------
        */

                $member->about_my_education = '';
                $member->education = '';
                $member->any_other_qualifications = '';

                /*
        |--------------------------------------------------------------------------
        | Career
        |--------------------------------------------------------------------------
        */

                $member->about_my_career = '';
                $member->employed_in = '';
                $member->occupation = '';
                $member->designation = '';
                $member->organization_name = '';
                $member->job_location = '';
                $member->annual_income = '';

                /*
        |--------------------------------------------------------------------------
        | Location
        |--------------------------------------------------------------------------
        */

                $member->country_living_in = '';
                $member->state_living_in = '';
                $member->city_living_in = '';
                $member->address_living_in = '';
                $member->native_place = '';

                /*
        |--------------------------------------------------------------------------
        | Family
        |--------------------------------------------------------------------------
        */

                $member->family_type = '';
                $member->family_status = '';

                $member->father_name = '';
                $member->father_occupation = '';

                $member->mother_name = '';
                $member->mother_occupation = '';

                $member->no_of_brothers = '';
                $member->no_of_sisters = '';
                $member->married_brothers = '';
                $member->married_sisters = '';

                $member->family_income = '';
                $member->about_family = '';

                /*
        |--------------------------------------------------------------------------
        | Lifestyle
        |--------------------------------------------------------------------------
        */

                $member->diet = '';
                $member->is_drinking = '';
                $member->is_smoking = '';
                $member->about_me = '';
                $member->any_disability = '';

                /*
        |--------------------------------------------------------------------------
        | Looking For
        |--------------------------------------------------------------------------
        */

                $member->looking_for = '';

                /*
        |--------------------------------------------------------------------------
        | Partner Preferences
        |--------------------------------------------------------------------------
        */

                $member->partner_age_from = '';
                $member->partner_age_to = '';

                $member->partner_country = '';
                $member->partner_religion = '';
                $member->partner_cast = '';

                $member->partner_height_from = 0;
                $member->partner_height_to = 0;

                $member->partner_education = '';
                $member->partner_mothertongue = '';

                $member->partner_annual_income_from = '';
                $member->partner_annual_income_to = '';

                $member->is_partner_manglik = '';

                $member->partner_occupation = '';
                $member->partner_state = '';
                $member->partner_city = '';
                $member->partner_diet = '';

                $member->is_partner_smoking = '';
                $member->is_partner_drinking = '';

                $member->about_my_partner = '';

                /*
        |--------------------------------------------------------------------------
        | Other fields
        |--------------------------------------------------------------------------
        */

                $member->google_token = '';
                $member->referral_code = '';
                $member->id_proof = '';

                /*
        |--------------------------------------------------------------------------
        | Profile photo
        |--------------------------------------------------------------------------
        */

                $member->photo = '';
                $member->photo_password = '';
                $member->photo_approved = '';

                /*
        |--------------------------------------------------------------------------
        | Profile status
        |--------------------------------------------------------------------------
        */

                $member->active = 'No';

                $member->member_type = '';
                $member->is_trusted = '';

                $member->profile_completed = '0';

                $member->promoted = '';

                /*
        |--------------------------------------------------------------------------
        | Profile visibility
        |--------------------------------------------------------------------------
        */

                $member->remarks = '';

                $member->relationship_manager = '';

                $member->profile_hide = '';
                $member->hide_for_days = '';
                $member->hidden_date = '';

                /*
        |--------------------------------------------------------------------------
        | Assignment / activation
        |--------------------------------------------------------------------------
        */

                $member->assigned_to = '';
                $member->pre_active = '';

                /*
        |--------------------------------------------------------------------------
        | FIRST INSERT
        |--------------------------------------------------------------------------
        |
        | This generates the numeric member ID.
        |
        */

                $member->save();

                /*
        |--------------------------------------------------------------------------
        | Generate real profile ID
        |--------------------------------------------------------------------------
        */

                $member->profile_id =
                    $prefix . $member->id;

                /*
        |--------------------------------------------------------------------------
        | Calculate profile completion
        |--------------------------------------------------------------------------
        */

                $member->profile_completed =
                    (string) $member->getProfileCompletion();

                /*
        |--------------------------------------------------------------------------
        | SECOND UPDATE
        |--------------------------------------------------------------------------
        */

                $member->save();

                return $member;
            }
        );

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            'Registration step 1 completed successfully.',

            'data' => [

                'registration_step' => 1,

                'next_step' => 2,

                'member' => [

                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'profile_created_for' =>
                    $member->profile_created_for,

                    'full_name' =>
                    $member->full_name,

                    'email' =>
                    $member->email,

                    'mobile_number' =>
                    $member->mobile_number,

                    'gender' =>
                    $member->gender,

                    'birth_date_time' =>
                    $member->birth_date_time,

                    'profile_completed' =>
                    $member->profile_completed,
                ],

                'application' => [

                    'id' =>
                    $application->id,

                    'name' =>
                    $application->name,

                    'code' =>
                    $application->code,
                ],
            ],

        ], 201);
    }

    public function registrationStep2(Request $request, $memberId): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Validate Step 2
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'birth_time' => [
                'required',
                'date_format:h:i A',
            ],

            'height' => [
                'required',
                'string',
                'max:255',
            ],

            'country_living_in' => [
                'required',
                'string',
                'max:255',
            ],

            'state_living_in' => [
                'required',
                'string',
                'max:255',
            ],

            'city_living_in' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Get current application
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
    | Find member
    |--------------------------------------------------------------------------
    */

        $member = Member::find($memberId);

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Update Step 2
    |--------------------------------------------------------------------------
    */

        DB::connection('application')->transaction(
            function () use ($member, $validated) {

                /*
            |--------------------------------------------------------------------------
            | Birth date + birth time
            |--------------------------------------------------------------------------
            |
            | Step 1 stores the date.
            | Step 2 supplies the time.
            |
            | Example:
            |
            | Step 1:
            | 1994-08-20
            |
            | Step 2:
            | 02:30 PM
            |
            | Result:
            | 1994-08-20 02:30:00 PM
            |
            */

                $birthDate = date(
                    'Y-m-d',
                    strtotime($member->birth_date_time)
                );

                $birthTime = date(
                    'h:i:s A',
                    strtotime($validated['birth_time'])
                );

                $member->birth_date_time =
                    $birthDate . ' ' . $birthTime;

                /*
            |--------------------------------------------------------------------------
            | Height
            |--------------------------------------------------------------------------
            */

                $member->height =
                    $validated['height'];

                /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */

                $member->country_living_in =
                    $validated['country_living_in'];

                $member->state_living_in =
                    $validated['state_living_in'];

                $member->city_living_in =
                    $validated['city_living_in'];

                /*
            |--------------------------------------------------------------------------
            | Recalculate profile completion
            |--------------------------------------------------------------------------
            */

                $member->profile_completed =
                    (string) $member->getProfileCompletion();

                $member->save();
            }
        );

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            'Registration step 2 completed successfully.',

            'data' => [

                'registration_step' => 2,

                'next_step' => 3,

                'member' => [

                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'full_name' =>
                    $member->full_name,

                    'birth_date_time' =>
                    $member->birth_date_time,

                    'height' =>
                    $member->height,

                    'country_living_in' =>
                    $member->country_living_in,

                    'state_living_in' =>
                    $member->state_living_in,

                    'city_living_in' =>
                    $member->city_living_in,

                    'profile_completed' =>
                    $member->profile_completed,
                ],

                'application' => [

                    'id' =>
                    $application->id,

                    'name' =>
                    $application->name,

                    'code' =>
                    $application->code,
                ],
            ],

        ], 200);
    }

    public function registrationStep3(
        Request $request,
        $memberId
    ): JsonResponse {

        /*
    |--------------------------------------------------------------------------
    | Validate Step 3
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'education' => [
                'required',
                'string',
                'max:255',
            ],

            'employed_in' => [
                'required',
                'string',
                'max:255',
            ],

            'occupation' => [
                'required',
                'string',
                'max:255',
            ],

            'annual_income' => [
                'required',
                'string',
                'max:255',
            ],

        ]);

        /*
    |--------------------------------------------------------------------------
    | Get application context
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
    | Find member
    |--------------------------------------------------------------------------
    */

        $member = Member::query()
            ->where('id', $memberId)
            ->first();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Update Step 3
    |--------------------------------------------------------------------------
    */

        $member = DB::connection('application')->transaction(
            function () use ($member, $validated) {

                /*
            |--------------------------------------------------------------------------
            | Education
            |--------------------------------------------------------------------------
            */

                $member->education =
                    $validated['education'];

                /*
            |--------------------------------------------------------------------------
            | Employment
            |--------------------------------------------------------------------------
            */

                $member->employed_in =
                    $validated['employed_in'];

                /*
            |--------------------------------------------------------------------------
            | Occupation
            |--------------------------------------------------------------------------
            */

                $member->occupation =
                    $validated['occupation'];

                /*
            |--------------------------------------------------------------------------
            | Annual Income
            |--------------------------------------------------------------------------
            */

                $member->annual_income =
                    $validated['annual_income'];

                /*
            |--------------------------------------------------------------------------
            | Calculate profile completion
            |--------------------------------------------------------------------------
            */

                $member->profile_completed =
                    (string) $member->getProfileCompletion();

                /*
            |--------------------------------------------------------------------------
            | Save
            |--------------------------------------------------------------------------
            */

                $member->save();

                return $member;
            }
        );

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            'Registration step 3 completed successfully.',

            'data' => [

                'registration_step' => 3,

                'next_step' => 4,

                'member' => [

                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'full_name' =>
                    $member->full_name,

                    'education' =>
                    $member->education,

                    'employed_in' =>
                    $member->employed_in,

                    'occupation' =>
                    $member->occupation,

                    'annual_income' =>
                    $member->annual_income,

                    'profile_completed' =>
                    $member->profile_completed,
                ],

                'application' => [

                    'id' =>
                    $application->id,

                    'name' =>
                    $application->name,

                    'code' =>
                    $application->code,
                ],
            ],

        ], 200);
    }

    public function registrationStep4(
        Request $request,
        $memberId
    ): JsonResponse {

        /*
    |--------------------------------------------------------------------------
    | Validate Step 4
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([

            'marital_status' => [
                'required',
                'string',
                'max:255',
            ],

            'mother_tongue' => [
                'required',
                'string',
                'max:255',
            ],

            'religion' => [
                'required',
                'string',
                'max:255',
            ],

            'cast' => [
                'required',
                'string',
                'max:255',
            ],

            'manglik' => [
                'required',
                'string',
                'max:255',
            ],

            'horoscope_needed' => [
                'required',
                'string',
                'max:136',
            ],

        ]);

        /*
    |--------------------------------------------------------------------------
    | Get application context
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
    | Find member
    |--------------------------------------------------------------------------
    */

        $member = Member::query()
            ->where('id', $memberId)
            ->first();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Update Step 4
    |--------------------------------------------------------------------------
    */

        $member = DB::connection('application')->transaction(
            function () use ($member, $validated) {

                /*
            |--------------------------------------------------------------------------
            | Marital Status
            |--------------------------------------------------------------------------
            */

                $member->marital_status =
                    $validated['marital_status'];

                /*
            |--------------------------------------------------------------------------
            | Mother Tongue
            |--------------------------------------------------------------------------
            */

                $member->mother_tongue =
                    $validated['mother_tongue'];

                /*
            |--------------------------------------------------------------------------
            | Religion
            |--------------------------------------------------------------------------
            */

                $member->religion =
                    $validated['religion'];

                /*
            |--------------------------------------------------------------------------
            | Caste
            |--------------------------------------------------------------------------
            */

                $member->cast =
                    $validated['cast'];

                /*
            |--------------------------------------------------------------------------
            | Manglik
            |--------------------------------------------------------------------------
            */

                $member->manglik =
                    $validated['manglik'];

                /*
            |--------------------------------------------------------------------------
            | Horoscope
            |--------------------------------------------------------------------------
            */

                $member->horoscope_needed =
                    $validated['horoscope_needed'];

                /*
            |--------------------------------------------------------------------------
            | Recalculate Profile Completion
            |--------------------------------------------------------------------------
            */

                $member->profile_completed =
                    (string) $member->getProfileCompletion();

                /*
            |--------------------------------------------------------------------------
            | Save
            |--------------------------------------------------------------------------
            */

                $member->save();

                return $member;
            }
        );

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            'Registration step 4 completed successfully.',

            'data' => [

                'registration_step' => 4,

                'next_step' => 5,

                'member' => [

                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'full_name' =>
                    $member->full_name,

                    'marital_status' =>
                    $member->marital_status,

                    'mother_tongue' =>
                    $member->mother_tongue,

                    'religion' =>
                    $member->religion,

                    'cast' =>
                    $member->cast,

                    'manglik' =>
                    $member->manglik,

                    'horoscope_needed' =>
                    $member->horoscope_needed,

                    'profile_completed' =>
                    $member->profile_completed,
                ],

                'application' => [

                    'id' =>
                    $application->id,

                    'name' =>
                    $application->name,

                    'code' =>
                    $application->code,
                ],
            ],

        ], 200);
    }

    public function registrationStep5(Request $request, $memberId): JsonResponse
    {

        /*
    |--------------------------------------------------------------------------
    | Validate Step 5
    |--------------------------------------------------------------------------
    |
    | Photo is optional because the user can skip this step.
    |
    */

        $validated = $request->validate([

            'photo' => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

        ]);

        /*
    |--------------------------------------------------------------------------
    | Get application context
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
    | Find member
    |--------------------------------------------------------------------------
    */

        $member = Member::query()
            ->where('id', $memberId)
            ->first();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }

        /*
|--------------------------------------------------------------------------
| Profile Photo Upload
|--------------------------------------------------------------------------
*/

        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            if (!$file->isValid()) {

                return response()->json([
                    'success' => false,
                    'message' => 'The uploaded photo is invalid.',
                ], 422);
            }

            /*
    |--------------------------------------------------------------------------
    | Photo Directory
    |--------------------------------------------------------------------------
    */

            $photoDirectory = public_path('photos/photo');

            if (!is_dir($photoDirectory)) {

                mkdir(
                    $photoDirectory,
                    0755,
                    true
                );
            }

            /*
    |--------------------------------------------------------------------------
    | Remove Existing Profile Photo
    |--------------------------------------------------------------------------
    */

            if (!empty($member->photo)) {

                $oldPhotoPath =
                    $photoDirectory . '/' . $member->photo;

                if (file_exists($oldPhotoPath)) {
                    unlink($oldPhotoPath);
                }
            }

            /*
    |--------------------------------------------------------------------------
    | Generate Filename
    |--------------------------------------------------------------------------
    */

            $extension =
                strtolower(
                    $file->getClientOriginalExtension()
                );

            $filename = 'member-photo-' . time() . '.' . $extension;

            /*
    |--------------------------------------------------------------------------
    | Move Photo
    |--------------------------------------------------------------------------
    */

            $file->move(
                $photoDirectory,
                $filename
            );

            /*
    |--------------------------------------------------------------------------
    | Save Filename
    |--------------------------------------------------------------------------
    */

            $member->photo = $filename;
        }

        /*
    |--------------------------------------------------------------------------
    | Profile Completion
    |--------------------------------------------------------------------------
    */

        $member->profile_completed =
            (string) $member->getProfileCompletion();

        /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

        $member->save();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([

            'success' => true,

            'message' =>
            $request->hasFile('photo')
                ? 'Registration completed successfully.'
                : 'Registration completed successfully without a profile photo.',

            'data' => [

                'registration_step' => 5,

                'next_step' => null,

                'dashboard' => true,

                'member' => [

                    'id' =>
                    $member->id,

                    'profile_id' =>
                    $member->profile_id,

                    'full_name' =>
                    $member->full_name,

                    'photo' =>
                    $member->photo,

                    'profile_completed' =>
                    $member->profile_completed,
                ],

                'application' => [

                    'id' =>
                    $application->id,

                    'name' =>
                    $application->name,

                    'code' =>
                    $application->code,
                ],
            ],

        ], 200);
    }

    /**
     * Change password for logged-in member.
     *
     * TEMPORARY:
     * Passwords are currently stored as plaintext.
     * Hashing will be implemented later.
     */
    public function changePassword(Request $request): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Validate request
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],

            'new_password' => [
                'required',
                'string',
                'min:6',
                'confirmed',
            ],
        ]);

    /*
    |--------------------------------------------------------------------------
    | Get logged-in member
    |--------------------------------------------------------------------------
    */

        /** @var \App\Models\Member|null $member */
        $member = $request->user();

        if (!$member) {

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
    |--------------------------------------------------------------------------
    | Verify current password
    |--------------------------------------------------------------------------
    |
    | TEMPORARY:
    | Password is currently stored as plaintext.
    |
    */

        if ($member->password !== $validated['current_password']) {

            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Prevent same password
    |--------------------------------------------------------------------------
    */

        if ($validated['current_password'] === $validated['new_password']) {

            return response()->json([
                'success' => false,
                'message' => 'New password must be different from your current password.',
            ], 422);
        }

        /*
    |--------------------------------------------------------------------------
    | Update password
    |--------------------------------------------------------------------------
    */

        $member->password = $validated['new_password'];

        $member->save();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ], 200);
    }
}
