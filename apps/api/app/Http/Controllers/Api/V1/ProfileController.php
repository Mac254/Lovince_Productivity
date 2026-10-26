<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Support\Contract;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    private const AVATAR_DISK = 'local';

    public function update(Request $request): UserResource
    {
        $request->user()->update($request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
        ]));

        return UserResource::make($request->user());
    }

    public function password(Request $request): Response
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        Log::info('auth.password_changed', ['user_id' => $request->user()->id, 'ip' => $request->ip()]);

        return response()->noContent();
    }

    /**
     * Raster images only: SVG can carry script, so it is never accepted.
     */
    public function storeAvatar(Request $request): UserResource
    {
        $request->validate([
            'avatar' => [
                'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.Contract::AVATAR_MAX_KB,
                'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096',
            ],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');
        $path = $file->storeAs('avatars', $user->id.'-'.Str::random(32).'.'.$file->extension(), self::AVATAR_DISK);

        $previous = $user->avatar_path;
        $user->forceFill(['avatar_path' => $path])->save();
        if ($previous) {
            Storage::disk(self::AVATAR_DISK)->delete($previous);
        }

        return UserResource::make($user);
    }

    public function showAvatar(Request $request): StreamedResponse
    {
        $path = $request->user()->avatar_path;
        abort_unless($path && Storage::disk(self::AVATAR_DISK)->exists($path), 404);

        return Storage::disk(self::AVATAR_DISK)->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'Content-Disposition' => 'inline',
        ]);
    }

    public function destroyAvatar(Request $request): UserResource
    {
        $user = $request->user();
        if ($user->avatar_path) {
            Storage::disk(self::AVATAR_DISK)->delete($user->avatar_path);
            $user->forceFill(['avatar_path' => null])->save();
        }

        return UserResource::make($user);
    }
}
