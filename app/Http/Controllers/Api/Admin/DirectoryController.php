<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ManagementUserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DirectoryController extends Controller
{
    public function students(): AnonymousResourceCollection
    {
        return $this->forRole('student');
    }

    public function lecturers(): AnonymousResourceCollection
    {
        return $this->forRole('lecturer');
    }

    public function admins(): AnonymousResourceCollection
    {
        return $this->forRole('admin');
    }

    private function forRole(string $role): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return ManagementUserResource::collection(User::query()->whereHas('roles', fn ($query) => $query->where('roles.name', $role))->with('roles:id,name')->orderBy('name')->get());
    }
}
