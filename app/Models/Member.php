<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'members';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    /**
     * Database connection.
     */
    public function getConnectionName()
    {
        return 'application';
    }

    /**
     * Hidden fields.
     */
    protected $hidden = [
        'password',
        'google_token',
        'photo_password',
    ];

    /**
     * Mass assignable fields.
     */
    protected $fillable = [

        // -------------------------------------------------
        // Basic
        // -------------------------------------------------
        'profile_id',
        'profile_created_for',
        'full_name',
        'email',
        'mobile_number',
        'alternate_number',
        'whatsapp_number',

        // -------------------------------------------------
        // Profile completion
        // -------------------------------------------------
        'profile_completed',

        // -------------------------------------------------
        // Personal
        // -------------------------------------------------
        'birth_date_time',
        'height',
        'gender',
        'blood_group',
        'health_info',
        'birth_place',
        'marital_status',
        'no_of_child',

        // -------------------------------------------------
        // Religion & Community
        // -------------------------------------------------
        'religion',
        'mother_tongue',
        'cast',
        'sub_cast',
        'gotra',
        'manglik',

        // -------------------------------------------------
        // Education
        // -------------------------------------------------
        'about_my_education',
        'education',
        'any_other_qualifications',

        // -------------------------------------------------
        // Career
        // -------------------------------------------------
        'about_my_career',
        'employed_in',
        'occupation',
        'designation',
        'organization_name',
        'job_location',
        'annual_income',

        // -------------------------------------------------
        // Location
        // -------------------------------------------------
        'country_living_in',
        'state_living_in',
        'city_living_in',
        'address_living_in',
        'native_place',

        // -------------------------------------------------
        // Family
        // -------------------------------------------------
        'family_type',
        'family_status',
        'father_name',
        'father_occupation',
        'mother_name',
        'mother_occupation',
        'no_of_brothers',
        'no_of_sisters',
        'married_brothers',
        'married_sisters',
        'family_income',
        'about_family',

        // -------------------------------------------------
        // Lifestyle
        // -------------------------------------------------
        'diet',
        'is_drinking',
        'is_smoking',
        'about_me',
        'any_disability',

        // -------------------------------------------------
        // Partner Preferences
        // -------------------------------------------------
        'looking_for',
        'partner_age_from',
        'partner_age_to',
        'partner_country',
        'partner_religion',
        'partner_cast',
        'partner_height_from',
        'partner_height_to',
        'partner_education',
        'partner_mothertongue',
        'partner_annual_income_from',
        'partner_annual_income_to',
        'is_partner_manglik',
        'partner_occupation',
        'partner_state',
        'partner_city',
        'partner_diet',
        'is_partner_smoking',
        'is_partner_drinking',
        'about_my_partner',
        'horoscope_needed',
        // Location
        'country_living_in',
        'state_living_in',
        'city_living_in',
        'address_living_in',
        'native_place',
    ];

    /**
     * Authentication identifier.
     */
    public function getAuthIdentifierName()
    {
        return 'id';
    }

    /**
     * Calculate profile completion percentage.
     *
     * The percentage is based only on the fields
     * defined below.
     */
    public function getProfileCompletion(): int
    {
        $completionFields = [

            // -------------------------------------------------
            // Basic
            // -------------------------------------------------
            'profile_created_for',
            'full_name',
            'mobile_number',
            'alternate_number',
            'whatsapp_number',
            'birth_date_time',
            'height',
            'gender',
            'blood_group',
            'health_info',
            'birth_place',

            // -------------------------------------------------
            // Religion & Community
            // -------------------------------------------------
            'religion',
            'mother_tongue',
            'cast',
            'sub_cast',
            'gotra',
            'manglik',

            // -------------------------------------------------
            // Personal
            // -------------------------------------------------
            'marital_status',
            'no_of_child',

            // -------------------------------------------------
            // Education & Career
            // -------------------------------------------------
            'about_my_education',
            'education',
            'any_other_qualifications',

            'about_my_career',
            'employed_in',
            'occupation',
            'designation',
            'organization_name',
            'job_location',
            'annual_income',

            // -------------------------------------------------
            // Location
            // -------------------------------------------------
            'country_living_in',
            'state_living_in',
            'city_living_in',
            'address_living_in',
            'native_place',

            // -------------------------------------------------
            // Family
            // -------------------------------------------------
            'family_type',
            'family_status',
            'father_name',
            'father_occupation',
            'mother_name',
            'mother_occupation',
            'no_of_brothers',
            'no_of_sisters',
            'married_brothers',
            'married_sisters',
            'family_income',
            'about_family',

            // -------------------------------------------------
            // Lifestyle
            // -------------------------------------------------
            'diet',
            'is_drinking',
            'is_smoking',
            'about_me',
            'any_disability',

            // -------------------------------------------------
            // Partner Preferences
            // -------------------------------------------------
            'looking_for',
            'partner_age_from',
            'partner_age_to',
            'partner_country',
            'partner_religion',
            'partner_cast',
            'partner_height_from',
            'partner_height_to',
            'partner_education',
            'partner_mothertongue',
            'partner_annual_income_from',
            'partner_annual_income_to',
            'is_partner_manglik',
            'partner_occupation',
            'partner_state',
            'partner_city',
            'partner_diet',
            'is_partner_smoking',
            'is_partner_drinking',
            'about_my_partner',
            'horoscope_needed',

            // Location
            'country_living_in',
            'state_living_in',
            'city_living_in',
            'address_living_in',
            'native_place',
        ];

        $totalFields = count($completionFields);

        if ($totalFields === 0) {
            return 0;
        }

        $completedFields = 0;

        foreach ($completionFields as $field) {

            $value = $this->{$field};

            /*
             * A field is considered complete when:
             *
             * - it is not NULL
             * - it is not an empty string
             * - it is not only whitespace
             */
            if (
                $value !== null &&
                trim((string) $value) !== ''
            ) {
                $completedFields++;
            }
        }

        return (int) round(
            ($completedFields / $totalFields) * 100
        );
    }

    /**
     * Recalculate and save profile completion.
     */
    public function updateProfileCompletion(): int
    {
        $this->profile_completed =
            $this->getProfileCompletion();

        $this->saveQuietly();

        return (int) $this->profile_completed;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(MemberPhoto::class, 'member_id');
    }
}
