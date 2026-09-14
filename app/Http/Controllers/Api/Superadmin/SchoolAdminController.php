<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Actions\Management\ManageUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\AssignSchoolAdminRequest;
use App\Http\Requests\Management\StatusRequest;
use App\Http\Requests\Management\StoreSchoolAdminRequest;
use App\Http\Resources\ManagementUserResource;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SchoolAdminController extends Controller
{
    public function index(School $school): AnonymousResourceCollection
    {
        Gate::authorize('view', $school);

        return ManagementUserResource::collection($school->admins()->with('roles:id,name')->orderBy('name')->get());
    }

    public function store(StoreSchoolAdminRequest $request, School $school, ManageUser $action)
    {
        Gate::authorize('update', $school);
        $result = $action->create($request->user(), $school, $request->validated(), 'admin');

        return response()->json(['data' => ManagementUserResource::make($result['user']), 'temporary_password' => $result['temporary_password']], 201);
    }

    public function assign(AssignSchoolAdminRequest $request, School $school, ManageUser $action): ManagementUserResource
    {
        $user = User::query()->findOrFail($request->integer('user_id'));

        return ManagementUserResource::make($action->assignSchoolAdmin($request->user(), $school, $user));
    }

    public function destroy(StatusRequest $request, School $school, User $user, ManageUser $action): ManagementUserResource
    {
        return ManagementUserResource::make($action->removeAdmin($request->user(), $school, $user));
    }
}
