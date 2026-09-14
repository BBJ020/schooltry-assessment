<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalDevelopmentSeeder may only run in the local environment.');
        }

        $password = env('SCHOOLTRY_DEV_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Set SCHOOLTRY_DEV_PASSWORD to at least 12 characters before running this seeder.');
        }

        $this->call(RoleSeeder::class);
        $school = School::query()->firstOrCreate(['slug' => 'schooltry-demo'], ['name' => 'SchoolTry Demo', 'email' => 'admin@schooltry.test', 'is_active' => true]);

        $accounts = [
            ['name' => 'Platform Superadmin', 'email' => 'superadmin@schooltry.test', 'school_id' => null, 'role' => 'superadmin'],
            ['name' => 'School Administrator', 'email' => 'admin@schooltry.test', 'school_id' => $school->id, 'role' => 'admin'],
            ['name' => 'Demo Lecturer', 'email' => 'lecturer@schooltry.test', 'school_id' => $school->id, 'role' => 'lecturer'],
            ['name' => 'Demo Student', 'email' => 'student@schooltry.test', 'school_id' => $school->id, 'role' => 'student'],
        ];

        foreach ($accounts as $account) {
            $roleName = $account['role'];
            unset($account['role']);
            $user = User::query()->where('email', $account['email'])->first() ?? new User;
            $user->fill(['name' => $account['name'], 'email' => $account['email'], 'password' => $password, 'is_active' => true]);
            $user->forceFill(['school_id' => $account['school_id']])->save();
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            DB::table('role_user')->where('user_id', $user->id)->delete();
            DB::table('role_user')->insert(['school_id' => $user->school_id, 'role_id' => $role->id, 'user_id' => $user->id]);
        }
    }
}
