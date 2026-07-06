<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'email'       => $this->email,
            'is_active'   => $this->is_active,
            'role'        => $this->whenLoaded('role', fn () => [
                'id'   => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
            ]),
            'permissions' => $this->whenLoaded('role', fn () =>
                $this->role?->permissions->pluck('slug') ?? []
            ),
            'company'     => $this->whenLoaded('company', fn () => [
                'id'   => $this->company->id,
                'name' => $this->company->name,
                'code' => $this->company->code,
            ]),
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}