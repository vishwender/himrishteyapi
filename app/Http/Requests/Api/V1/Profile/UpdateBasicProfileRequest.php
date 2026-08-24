<?php

namespace App\Http\Requests\Api\V1\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBasicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'profile_created_for' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
            ],

            'full_name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'mobile_number' => [
                'sometimes',
                'required',
                'string',
                'max:30',
            ],

            'alternate_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'whatsapp_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'birth_date_time' => [
                'sometimes',
                'nullable',
                'date',
            ],

            'height' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'gender' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
            ],

            'blood_group' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'health_info' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'birth_place' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }
}
