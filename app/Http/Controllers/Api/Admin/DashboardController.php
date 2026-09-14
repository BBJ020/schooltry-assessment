<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $roleCount = fn (string $role): int => User::query()->whereHas('roles', fn ($query) => $query->where('roles.name', $role))->count();

        return response()->json(['data' => [
            'total_students' => $roleCount('student'),
            'total_lecturers' => $roleCount('lecturer'),
            'total_admins' => $roleCount('admin'),
            'active_users' => User::query()->where('is_active', true)->count(),
            'inactive_users' => User::query()->where('is_active', false)->count(),
            'total_courses' => Course::query()->count(),
        ]]);
    }
}
