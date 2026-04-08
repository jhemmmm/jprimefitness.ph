<?php

namespace App\Services;

use App\Models\BusinessProfile;

class BusinessProfileContext
{
    private ?BusinessProfile $resolvedProfile = null;

    public function profile(): BusinessProfile
    {
        if ($this->resolvedProfile !== null) {
            return $this->resolvedProfile;
        }

        $this->resolvedProfile = BusinessProfile::query()->first();

        if ($this->resolvedProfile !== null) {
            return $this->resolvedProfile;
        }

        $this->resolvedProfile = BusinessProfile::query()->create(
            BusinessProfile::defaultAttributes()
        );

        return $this->resolvedProfile;
    }

    /**
     * @return array{id:int, name:string, city:?string, province:?string, status:?string}
     */
    public function legacyLocation(): array
    {
        $profile = $this->profile();

        return [
            'id' => $profile->id,
            'name' => $profile->name,
            'city' => $profile->city,
            'province' => $profile->province,
            'status' => $profile->status,
        ];
    }
}
