<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'profile_id' => $this->profile_id,
            'name' => $this->full_name,

            'email' => $this->email,
            'mobile_number' => $this->mobile_number,

            'gender' => $this->gender,
            'height' => $this->height,

            'religion' => $this->religion,
            'mother_tongue' => $this->mother_tongue,

            'marital_status' => $this->marital_status,

            'location' => [
                'country' => $this->country_living_in,
                'state' => $this->state_living_in,
                'city' => $this->city_living_in,
            ],

            'photo' => $this->photo,

            'profile_completed' => $this->profile_completed,
        ];
    }
}
