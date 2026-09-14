<?php

namespace App\Http\Controllers\Api\Management;

use App\Actions\Management\ManageUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ChangeRoleRequest;
use App\Http\Requests\Management\StatusRequest;
use App\Http\Requests\Management\UpdateManagedUserRequest;
use App\Http\Resources\ManagementUserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserMutationController extends Controller
{
    public function update(UpdateManagedUserRequest $request, User $user, ManageUser $action): ManagementUserResource
    {
        return ManagementUserResource::make($action->update($request->user(), $user, $request->validated()));
    }

    public function role(ChangeRoleRequest $request, User $user, ManageUser $action): ManagementUserResource
    {
        return ManagementUserResource::make($action->changeRole($request->user(), $user, $request->string('role')->toString()));
    }

    public function activate(StatusRequest $request, User $user, ManageUser $action): ManagementUserResource
    {
        return ManagementUserResource::make($action->setActive($request->user(), $user, true));
    }

    public function deactivate(StatusRequest $request, User $user, ManageUser $action): ManagementUserResource
    {
        return ManagementUserResource::make($action->setActive($request->user(), $user, false));
    }

    public function resetPassword(StatusRequest $request, User $user, ManageUser $action): JsonResponse
    {
        $result = $action->resetPassword($request->user(), $user);

        return response()->json(['data' => ManagementUserResource::make($result['user']), 'temporary_password' => $result['temporary_password']]);
    }
}
