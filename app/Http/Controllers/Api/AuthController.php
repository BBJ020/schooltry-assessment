<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are invalid.'], 401);
        }

        $isSuperadmin = $user->school_id === null && $user->hasRole('superadmin');
        $hasActiveTenant = $user->school_id !== null && $user->school()->where('is_active', true)->exists();

        if (! $user->is_active || (! $isSuperadmin && ! $hasActiveTenant)) {
            return response()->json(['message' => 'This account is unavailable.'], 403);
        }

        $token = $user->createToken($request->validated('device_name', 'schooltry-api'))->plainTextToken;
        $tenant = app(TenantContext::class);
        if (! $isSuperadmin) {
            $tenant->setFor($user);
        }

        try {
            $user->load(['school:id,name,slug', 'roles:id,name']);
        } finally {
            $tenant->clear();
        }

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token,
            'user' => UserResource::make($user),
        ]);
    }

    public function logout(LogoutRequest $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load(['school:id,name,slug', 'roles:id,name']));
    }
}
