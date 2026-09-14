<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Management\ManageUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreManagedUserRequest;
use App\Http\Resources\ManagementUserResource;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $search = trim((string) $request->query('search'));
        $role = (string) $request->query('role');
        $users = User::query()->with('roles:id,name')
            ->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->when(in_array($role, ['admin', 'lecturer', 'student'], true), fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('roles.name', $role)))
            ->orderBy('name')->paginate(25)->withQueryString();

        return ManagementUserResource::collection($users);
    }

    public function store(StoreManagedUserRequest $request, ManageUser $action)
    {
        $school = School::query()->findOrFail($request->user()->school_id);
        $validated = $request->validated();
        $role = $validated['role'];
        unset($validated['role']);
        $result = $action->create($request->user(), $school, $validated, $role);

        return response()->json(['data' => ManagementUserResource::make($result['user']), 'temporary_password' => $result['temporary_password']], 201);
    }

    public function show(User $user): ManagementUserResource
    {
        Gate::authorize('view', $user);

        return ManagementUserResource::make($user->load(['roles:id,name', 'taughtCourses.lecturer:id,name', 'enrolledCourses.lecturer:id,name']));
    }
}
