<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WallpaperResource;
use App\Models\Wallpaper;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Personal clock wallpapers. Raster images only (SVG can carry script), stored privately
 * and streamed back to their owner; other users get 404.
 */
class WallpaperController extends Controller
{
    private const DISK = 'local';

    public function index(Request $request): AnonymousResourceCollection
    {
        return WallpaperResource::collection($request->user()->wallpapers()->latest('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'image' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Contract::WALLPAPER_MAX_KB,
                'dimensions:min_width=320,min_height=240,max_width=8000,max_height=8000',
            ],
        ]);

        $user = $request->user();
        if ($user->wallpapers()->count() >= Contract::WALLPAPER_LIMIT) {
            throw ValidationException::withMessages([
                'image' => 'You can keep up to '.Contract::WALLPAPER_LIMIT.' wallpapers. Remove one first.',
            ]);
        }

        $file = $request->file('image');
        $path = $file->storeAs('wallpapers', $user->id.'-'.Str::random(32).'.'.$file->extension(), self::DISK);
        $name = Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) ?: 'Wallpaper', 110, '');

        $wallpaper = $user->wallpapers()->create(['path' => $path, 'name' => $name]);

        return WallpaperResource::make($wallpaper)->response()->setStatusCode(201);
    }

    public function image(Request $request, int $wallpaper): StreamedResponse
    {
        $record = $this->owned($request, $wallpaper);
        abort_unless(Storage::disk(self::DISK)->exists($record->path), 404);

        return Storage::disk(self::DISK)->response($record->path, null, [
            'Cache-Control' => 'private, max-age=604800, immutable',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroy(Request $request, int $wallpaper): Response
    {
        $record = $this->owned($request, $wallpaper);
        Storage::disk(self::DISK)->delete($record->path);
        $record->delete();

        return response()->noContent();
    }

    private function owned(Request $request, int $id): Wallpaper
    {
        return $request->user()->wallpapers()->findOrFail($id);
    }
}
