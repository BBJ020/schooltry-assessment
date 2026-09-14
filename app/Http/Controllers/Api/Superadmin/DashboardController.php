<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $roleCount = fn (string $role): int => User::query()->whereHas('roles', fn ($query) => $query->where('roles.name', $role))->count();

        return response()->json(['data' => [
            'total_schools' => School::query()->count(),
            'active_schools' => School::query()->where('is_active', true)->count(),
            'inactive_schools' => School::query()->where('is_active', false)->count(),
            'total_admins' => $roleCount('admin'),
            'total_lecturers' => $roleCount('lecturer'),
            'total_students' => $roleCount('student'),
            'total_courses' => Course::query()->count(),
        ]]);
    }
}
