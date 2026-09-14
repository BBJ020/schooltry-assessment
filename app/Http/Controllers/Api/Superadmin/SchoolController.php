<?php

namespace App\Http\Controllers\Api\Superadmin;

use App\Actions\Management\ManageSchool;
use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StatusRequest;
use App\Http\Requests\Management\StoreSchoolRequest;
use App\Http\Requests\Management\UpdateSchoolRequest;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SchoolController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', School::class);
        $search = trim((string) $request->query('search'));
        $schools = School::query()->when($search, fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->withCount(['users', 'courses'])->withRoleCounts()->orderBy('name')->paginate(25)->withQueryString();

        return SchoolResource::collection($schools);
    }

    public function store(StoreSchoolRequest $request, ManageSchool $action): SchoolResource
    {
        return SchoolResource::make($action->create($request->user(), $request->validated()));
    }

    public function show(School $school): SchoolResource
    {
        Gate::authorize('view', $school);
        $school = School::query()->whereKey($school->id)->withCount(['users', 'courses'])->withRoleCounts()->with(['admins.roles:id,name'])->firstOrFail();

        return SchoolResource::make($school);
    }

    public function update(UpdateSchoolRequest $request, School $school, ManageSchool $action): SchoolResource
    {
        return SchoolResource::make($action->update($request->user(), $school, $request->validated()));
    }

    public function activate(StatusRequest $request, School $school, ManageSchool $action): SchoolResource
    {
        return SchoolResource::make($action->setActive($request->user(), $school, true));
    }

    public function deactivate(StatusRequest $request, School $school, ManageSchool $action): SchoolResource
    {
        return SchoolResource::make($action->setActive($request->user(), $school, false));
    }
}
