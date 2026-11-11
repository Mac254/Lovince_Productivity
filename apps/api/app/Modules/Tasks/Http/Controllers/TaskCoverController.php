<?php

namespace App\Modules\Tasks\Http\Controllers;

use App\Modules\Tasks\Http\Resources\TaskResource;
use App\Modules\Tasks\Models\Task;
use App\Shared\Http\Controllers\Controller;
use App\Shared\Support\WebpImage;
use App\Support\Contract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskCoverController extends Controller
{
    public function show(Task $task): StreamedResponse
    {
        Gate::authorize('view', $task);
        abort_unless($task->cover_path && Storage::disk(Task::COVER_DISK)->exists($task->cover_path), 404);

        return Storage::disk(Task::COVER_DISK)->response($task->cover_path, null, [
            'Cache-Control' => 'private, max-age=604800, immutable',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function store(Request $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);
        $request->validate([
            'image' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Contract::COVER_MAX_KB,
                'dimensions:min_width=320,min_height=180,max_width=8000,max_height=8000',
            ],
        ]);

        $file = $request->file('image');
        $path = WebpImage::store($file, 'task-covers', $task->user_id.'-'.Str::random(32), Task::COVER_DISK, 1600, 'image');
        $task->removeUploadedCover();
        $task->forceFill(['cover' => null, 'cover_path' => $path])->save();

        return $this->resource($task);
    }

    public function destroy(Task $task): TaskResource
    {
        Gate::authorize('update', $task);
        $task->removeUploadedCover();
        $task->forceFill(['cover' => null, 'cover_path' => null])->save();

        return $this->resource($task);
    }

    private function resource(Task $task): TaskResource
    {
        return TaskResource::make($task->load(Task::RELATIONS)->loadSum('notes', 'minutes')->loadChecklistCounts());
    }
}
