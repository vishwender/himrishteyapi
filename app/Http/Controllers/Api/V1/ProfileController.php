<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\UpdateBasicProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Member;

class ProfileController extends Controller
{

    /**
     * standard member profile response.
     */
    private function memberData(Member $member): array
    {
        return [
            'id' => $member->id,
            'profile_id' => $member->profile_id,

            // Profile completion
            'profile_completed' => (int) $member->profile_completed,

            // Photo
            'photo' => $member->photo,

            // Basic
            'profile_created_for' => $member->profile_created_for,
            'full_name' => $member->full_name,
            'email' => $member->email,
            'mobile_number' => $member->mobile_number,
            'alternate_number' => $member->alternate_number,
            'whatsapp_number' => $member->whatsapp_number,

            // Personal
            'birth_date_time' => $member->birth_date_time,
            'birth_place' => $member->birth_place,
            'gender' => $member->gender,
            'height' => $member->height,
            'blood_group' => $member->blood_group,
            'health_info' => $member->health_info,
            'marital_status' => $member->marital_status,
            'no_of_child' => $member->no_of_child,

            // Religion & Community
            'religion' => $member->religion,
            'mother_tongue' => $member->mother_tongue,
            'cast' => $member->cast,
            'sub_cast' => $member->sub_cast,
            'gotra' => $member->gotra,
            'manglik' => $member->manglik,
            'horoscope_needed' => $member->horoscope_needed,

            // Education
            'about_my_education' => $member->about_my_education,
            'education' => $member->education,
            'any_other_qualifications' => $member->any_other_qualifications,

            // Career
            'about_my_career' => $member->about_my_career,
            'employed_in' => $member->employed_in,
            'occupation' => $member->occupation,
            'designation' => $member->designation,
            'organization_name' => $member->organization_name,
            'job_location' => $member->job_location,
            'annual_income' => $member->annual_income,

            // Location
            'country_living_in' => $member->country_living_in,
            'state_living_in' => $member->state_living_in,
            'city_living_in' => $member->city_living_in,
            'address_living_in' => $member->address_living_in,
            'native_place' => $member->native_place,

            // Family
            'family_type' => $member->family_type,
            'family_status' => $member->family_status,
            'father_name' => $member->father_name,
            'father_occupation' => $member->father_occupation,
            'mother_name' => $member->mother_name,
            'mother_occupation' => $member->mother_occupation,
            'no_of_brothers' => $member->no_of_brothers,
            'no_of_sisters' => $member->no_of_sisters,
            'married_brothers' => $member->married_brothers,
            'married_sisters' => $member->married_sisters,
            'family_income' => $member->family_income,
            'about_family' => $member->about_family,

            // Lifestyle
            'diet' => $member->diet,
            'is_drinking' => $member->is_drinking,
            'is_smoking' => $member->is_smoking,
            'about_me' => $member->about_me,
            'any_disability' => $member->any_disability,

            // Partner Preferences
            'looking_for' => $member->looking_for,
            'partner_age_from' => $member->partner_age_from,
            'partner_age_to' => $member->partner_age_to,
            'partner_country' => $member->partner_country,
            'partner_religion' => $member->partner_religion,
            'partner_cast' => $member->partner_cast,
            'partner_height_from' => $member->partner_height_from,
            'partner_height_to' => $member->partner_height_to,
            'partner_education' => $member->partner_education,
            'partner_mothertongue' => $member->partner_mothertongue,
            'partner_annual_income_from' => $member->partner_annual_income_from,
            'partner_annual_income_to' => $member->partner_annual_income_to,
            'is_partner_manglik' => $member->is_partner_manglik,
            'partner_occupation' => $member->partner_occupation,
            'partner_state' => $member->partner_state,
            'partner_city' => $member->partner_city,
            'partner_diet' => $member->partner_diet,
            'is_partner_smoking' => $member->is_partner_smoking,
            'is_partner_drinking' => $member->is_partner_drinking,
            'about_my_partner' => $member->about_my_partner,
        ];
    }
    /**
     * View logged-in member profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        return response()->json([
            'success' => true,

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update basic profile information.
     */
    public function updateBasic(
        UpdateBasicProfileRequest $request
    ): JsonResponse {

        /** @var \App\Models\Member $member */
        $member = $request->user();

        $data = $request->validated();

        $allowedFields = [
            'profile_created_for',
            'full_name',
            'mobile_number',
            'alternate_number',
            'whatsapp_number',
        ];

        $data = array_intersect_key(
            $data,
            array_flip($allowedFields)
        );

        $member->fill($data);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Basic profile updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update personal details.
     */
    public function updatePersonal(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'birth_date_time' => [
                'sometimes',
                'nullable',
                'date_format:Y-m-d h:i A',
            ],

            'birth_place' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'gender' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'height' => [
                'sometimes',
                'nullable',
                'regex:/^\d+ft\s\d+in$/',
            ],

            'blood_group' => [
                'sometimes',
                'nullable',
                'string',
                'max:10',
            ],

            'marital_status' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'no_of_child' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:20',
            ],

            'health_info' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Personal details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update religion and community details.
     */
    public function updateReligion(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'religion' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'mother_tongue' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'sub_cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'gotra' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'manglik' => [
                'sometimes',
                'nullable',
                'in:Yes,No',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Religion and community details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update education and career details.
     */
    public function updateEducationCareer(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'about_my_education' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'education' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'any_other_qualifications' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],

            'about_my_career' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'employed_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'designation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'organization_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'job_location' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'annual_income' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Education and career details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update location details.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'country_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'state_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'city_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'address_living_in' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'native_place' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Location details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update family details.
     */
    public function updateFamily(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'family_type' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'family_status' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'father_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'father_occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'mother_name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'mother_occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'no_of_brothers' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:20',
            ],

            'no_of_sisters' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:20',
            ],

            'married_brothers' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:20',
            ],

            'married_sisters' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:20',
            ],

            'family_income' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'about_family' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Family details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update lifestyle details.
     */
    public function updateLifestyle(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([

            'diet' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'is_drinking' => [
                'sometimes',
                'nullable',
                'in:Yes,No',
            ],

            'is_smoking' => [
                'sometimes',
                'nullable',
                'in:Yes,No',
            ],

            'about_me' => [
                'sometimes',
                'nullable',
                'string',
                'max:3000',
            ],

            'any_disability' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Lifestyle details updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }

    /**
     * Update partner preferences.
     */
    public function updatePartnerPreferences(Request $request): JsonResponse
    {
        /** @var \App\Models\Member $member */
        $member = $request->user();

        $validated = $request->validate([
            'looking_for' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'partner_age_from' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
            ],

            'partner_age_to' => [
                'sometimes',
                'nullable',
                'integer',
                'min:18',
                'max:100',
                'gte:partner_age_from',
            ],

            'partner_country' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'partner_religion' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'partner_cast' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'partner_height_from' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:3',
                'max:8',
            ],

            'partner_height_to' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:3',
                'max:8',
                'gte:partner_height_from',
            ],

            'partner_education' => [
                'sometimes',
                'nullable',
                'string',
                'max:1000',
            ],

            'partner_mothertongue' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'partner_annual_income_from' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'partner_annual_income_to' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'is_partner_manglik' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'partner_occupation' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'partner_state' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'partner_city' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],

            'partner_diet' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'is_partner_smoking' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'is_partner_drinking' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            'horoscope_needed' => [
                'sometimes',
                'nullable',
                'in:Yes,No',
            ],

            'about_my_partner' => [
                'sometimes',
                'nullable',
                'string',
            ],
        ]);

        $member->fill($validated);

        $member->profile_completed =
            $member->getProfileCompletion();

        $member->save();

        return response()->json([
            'success' => true,
            'message' => 'Partner preferences updated successfully.',

            'data' => [
                'member' => $this->memberData($member),
            ],
        ]);
    }
}
