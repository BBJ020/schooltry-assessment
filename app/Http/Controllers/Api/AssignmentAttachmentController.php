<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentAttachmentController extends Controller
{
    public function __invoke(Request $request, int $assignment): StreamedResponse
    {
        $visibleAssignment = Assignment::query()->visibleTo($request->user())->findOrFail($assignment);
        Gate::forUser($request->user())->authorize('view', $visibleAssignment);

        abort_unless($visibleAssignment->file_path !== null, 404);

        $disk = Storage::disk((string) config('filesystems.default', 'local'));
        abort_unless($disk->exists($visibleAssignment->file_path), 404);

        return $disk->download(
            $visibleAssignment->file_path,
            $visibleAssignment->file_name ?: 'assignment-attachment',
            ['Content-Type' => $visibleAssignment->file_mime_type ?: 'application/octet-stream'],
        );
    }
}
