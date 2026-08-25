<?php

namespace App\Services;

use App\Models\Member;
use Illuminate\Support\Collection;

class ProfileMatchingService
{
    /**
     * Get best matching profiles for a member.
     *
     * @param Member $member
     * @param int $limit
     * @return Collection
     */
    public function getMatches(
        Member $member,
        int $limit = 10
    ): Collection {

        /*
        |--------------------------------------------------------------------------
        | Determine opposite gender
        |--------------------------------------------------------------------------
        */

        $gender = strtolower(trim((string) $member->gender));

        if ($gender === 'male') {

            $oppositeGender = 'female';
        } elseif ($gender === 'female') {

            $oppositeGender = 'male';
        } else {

            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Get candidate profiles
        |--------------------------------------------------------------------------
        |
        | We only fetch profiles that can actually appear in search.
        |
        */

        $candidates = Member::query()
            ->where('id', '!=', $member->id)

            ->whereRaw(
                'LOWER(TRIM(gender)) = ?',
                [$oppositeGender]
            )

            /*
             * Active profiles only
             */
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(active)) = ?', ['yes']);
            })

            /*
             * Hidden profiles should not appear
             */
            ->where(function ($query) {
                $query
                    ->whereNull('profile_hide')
                    ->orWhere('profile_hide', '')
                    ->orWhereRaw(
                        'LOWER(TRIM(profile_hide)) = ?',
                        ['no']
                    );
            })

            ->select([
                'id',
                'profile_id',
                'full_name',
                'gender',
                'birth_date_time',
                'height',
                'religion',
                'mother_tongue',
                'cast',
                'sub_cast',
                'marital_status',
                'education',
                'occupation',
                'annual_income',
                'country_living_in',
                'state_living_in',
                'city_living_in',
                'diet',
                'is_smoking',
                'is_drinking',
                'manglik',
                'photo',
                'profile_completed',
                'promoted',
            ])
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Calculate match score
        |--------------------------------------------------------------------------
        */

        $matches = $candidates->map(function ($candidate) use ($member) {

            $score = 0;

            /*
            |--------------------------------------------------------------------------
            | Age
            |--------------------------------------------------------------------------
            */

            if (
                $this->hasValue($member->partner_age_from) ||
                $this->hasValue($member->partner_age_to)
            ) {

                $age = $this->calculateAge(
                    $candidate->birth_date_time
                );

                if ($age !== null) {

                    $from = $this->hasValue(
                        $member->partner_age_from
                    )
                        ? (int) $member->partner_age_from
                        : 18;

                    $to = $this->hasValue(
                        $member->partner_age_to
                    )
                        ? (int) $member->partner_age_to
                        : 100;

                    if ($age >= $from && $age <= $to) {
                        $score += 20;
                    }
                }
            } else {

                /*
                 * No preference specified.
                 * Don't penalize the profile.
                 */
            }

            /*
            |--------------------------------------------------------------------------
            | Religion
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->religion,
                    $member->partner_religion
                )
            ) {
                $score += 15;
            }

            /*
            |--------------------------------------------------------------------------
            | Caste
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->cast,
                    $member->partner_cast
                )
            ) {
                $score += 15;
            }

            /*
            |--------------------------------------------------------------------------
            | Height
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesHeight(
                    $candidate->height,
                    $member->partner_height_from,
                    $member->partner_height_to
                )
            ) {
                $score += 10;
            }

            /*
            |--------------------------------------------------------------------------
            | Education
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->education,
                    $member->partner_education
                )
            ) {
                $score += 10;
            }

            /*
            |--------------------------------------------------------------------------
            | Mother Tongue
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->mother_tongue,
                    $member->partner_mothertongue
                )
            ) {
                $score += 10;
            }

            /*
            |--------------------------------------------------------------------------
            | Occupation
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->occupation,
                    $member->partner_occupation
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Diet
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->diet,
                    $member->partner_diet
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Manglik
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->manglik,
                    $member->is_partner_manglik
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Smoking
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->is_smoking,
                    $member->is_partner_smoking
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Drinking
            |--------------------------------------------------------------------------
            */

            if (
                $this->matchesValue(
                    $candidate->is_drinking,
                    $member->is_partner_drinking
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */

            if (
                $this->hasValue($member->partner_state) &&
                $this->matchesValue(
                    $candidate->state_living_in,
                    $member->partner_state
                )
            ) {
                $score += 5;
            }

            /*
            |--------------------------------------------------------------------------
            | Return formatted result
            |--------------------------------------------------------------------------
            */

            return [
                'id' => $candidate->id,

                'profile_id' => $candidate->profile_id,

                'full_name' => $candidate->full_name,

                'age' => $this->calculateAge(
                    $candidate->birth_date_time
                ),

                'gender' => $candidate->gender,

                'height' => $candidate->height,

                'religion' => $candidate->religion,

                'mother_tongue' => $candidate->mother_tongue,

                'cast' => $candidate->cast,

                'marital_status' => $candidate->marital_status,

                'education' => $candidate->education,

                'occupation' => $candidate->occupation,

                'annual_income' => $candidate->annual_income,

                'country_living_in' =>
                $candidate->country_living_in,

                'state_living_in' =>
                $candidate->state_living_in,

                'city_living_in' =>
                $candidate->city_living_in,

                'photo' => $candidate->photo,

                'profile_completed' =>
                (int) $candidate->profile_completed,

                'match_percentage' => min($score, 100),

                '_score' => $score,

                '_promoted' =>
                strtolower(trim((string) $candidate->promoted))
                    === 'yes' ? 1 : 0,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Sort
        |--------------------------------------------------------------------------
        |
        | 1. Highest match first
        | 2. Promoted profiles next
        |
        */

        return $matches
            ->sort(function ($a, $b) {

                if ($a['_score'] === $b['_score']) {
                    return $b['_promoted'] <=> $a['_promoted'];
                }

                return $b['_score'] <=> $a['_score'];
            })
            ->take($limit)
            ->map(function ($member) {

                unset(
                    $member['_score'],
                    $member['_promoted']
                );

                return $member;
            })
            ->values();
    }

    /**
     * Check whether a value is actually populated.
     */
    private function hasValue($value): bool
    {
        return $value !== null &&
            trim((string) $value) !== '';
    }

    /**
     * Compare stored value with preference.
     *
     * Supports comma-separated preference values.
     */
    private function matchesValue(
        $candidateValue,
        $preference
    ): bool {

        if (!$this->hasValue($preference)) {
            return false;
        }

        if (!$this->hasValue($candidateValue)) {
            return false;
        }

        $candidateValue = strtolower(
            trim((string) $candidateValue)
        );

        $preferences = array_map(
            fn($value) => strtolower(trim($value)),
            explode(',', (string) $preference)
        );

        foreach ($preferences as $preferred) {

            if ($preferred === '') {
                continue;
            }

            /*
             * Exact match
             */
            if ($candidateValue === $preferred) {
                return true;
            }

            /*
             * Useful for values such as:
             *
             * "Brahmin, Rajput"
             */
            if (
                str_contains(
                    $candidateValue,
                    $preferred
                ) ||
                str_contains(
                    $preferred,
                    $candidateValue
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check candidate height against preference range.
     *
     * Supports:
     * 5ft 6in
     * 5ft
     * 5.5
     * 5.6
     */
    private function matchesHeight(
        $candidateHeight,
        $heightFrom,
        $heightTo
    ): bool {

        if (!$this->hasValue($candidateHeight)) {
            return false;
        }

        if (
            !$this->hasValue($heightFrom) &&
            !$this->hasValue($heightTo)
        ) {
            return false;
        }

        $candidateHeightInches =
            $this->heightToInches($candidateHeight);

        if ($candidateHeightInches === null) {
            return false;
        }

        $from = $this->hasValue($heightFrom)
            ? $this->heightToInches($heightFrom)
            : null;

        $to = $this->hasValue($heightTo)
            ? $this->heightToInches($heightTo)
            : null;

        if (
            $from !== null &&
            $candidateHeightInches < $from
        ) {
            return false;
        }

        if (
            $to !== null &&
            $candidateHeightInches > $to
        ) {
            return false;
        }

        return true;
    }

    /**
     * Convert height to inches.
     */
    private function heightToInches($height): ?float
    {
        if ($height === null || $height === '') {
            return null;
        }

        $height = strtolower(
            trim((string) $height)
        );

        /*
        |--------------------------------------------------------------------------
        | 5ft 6in
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/(\d+(?:\.\d+)?)\s*ft\s*(\d+(?:\.\d+)?)?\s*in?/',
                $height,
                $matches
            )
        ) {

            $feet = (float) $matches[1];

            $inches = isset($matches[2])
                ? (float) $matches[2]
                : 0;

            return ($feet * 12) + $inches;
        }

        /*
        |--------------------------------------------------------------------------
        | 5'6"
        |--------------------------------------------------------------------------
        */

        if (
            preg_match(
                '/(\d+(?:\.\d+)?)\s*[\'′]\s*(\d+(?:\.\d+)?)?\s*["″]?/',
                $height,
                $matches
            )
        ) {

            $feet = (float) $matches[1];

            $inches = isset($matches[2])
                ? (float) $matches[2]
                : 0;

            return ($feet * 12) + $inches;
        }

        /*
        |--------------------------------------------------------------------------
        | Numeric height
        |--------------------------------------------------------------------------
        |
        | Existing partner preference fields are stored as DOUBLE,
        | e.g. 5.5.
        |
        */

        if (is_numeric($height)) {

            $value = (float) $height;

            /*
             * 5.5 means 5 feet 5 inches in the existing system,
             * rather than 5.5 literal feet.
             */
            $feet = floor($value);
            $decimal = $value - $feet;

            if ($decimal > 0) {

                $inches = round($decimal * 10);

                return ($feet * 12) + $inches;
            }

            return $feet * 12;
        }

        return null;
    }

    /**
     * Calculate age from stored birth date.
     */
    private function calculateAge(
        ?string $birthDate
    ): ?int {

        if (!$this->hasValue($birthDate)) {
            return null;
        }

        try {

            $date = \Carbon\Carbon::parse($birthDate);

            return $date->age;
        } catch (\Throwable $e) {

            return null;
        }
    }
}
