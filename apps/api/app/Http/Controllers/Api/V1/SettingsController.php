<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\User;
use App\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The signed-in user's settings, joined from every module in App\Settings\Modules:
 * `modules` describes each setting for the Settings page, `values` is what the app applies.
 */
class SettingsController extends Controller
{
    public function __construct(private SettingsRegistry $registry) {}

    public function show(Request $request): JsonResponse
    {
        return $this->respond($request->user());
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['settings' => $this->registry->merge($user->settings, $request->changes()) ?: null])->save();

        return $this->respond($user);
    }

    /** Puts one module (`?module=clock`) or every setting back to its default. */
    public function destroy(Request $request): JsonResponse
    {
        $module = $request->validate([
            'module' => ['sometimes', 'string', Rule::in(array_keys($this->registry->modules()))],
        ])['module'] ?? null;

        $user = $request->user();
        $user->forceFill(['settings' => $this->registry->reset($user->settings, $module) ?: null])->save();

        return $this->respond($user);
    }

    private function respond(User $user): JsonResponse
    {
        return response()->json(['data' => [
            'modules' => $this->registry->describe($user->settings),
            'values' => $this->registry->resolve($user->settings),
        ]]);
    }
}
