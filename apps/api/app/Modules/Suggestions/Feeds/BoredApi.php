<?php

namespace App\Modules\Suggestions\Feeds;

use App\Modules\Suggestions\Contracts\IdeaFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls random activities from the keyless Bored API, one per request. Activity types map onto
 * categories through `suggestions.feed.types`; unmapped types and ones not marked kid friendly are
 * skipped. The first failed or malformed response ends the pull.
 */
class BoredApi implements IdeaFeed
{
    public function fetch(int $count): array
    {
        $config = config('suggestions.feed');
        $ideas = [];
        for ($pull = 0; $pull < $count; $pull++) {
            try {
                $response = Http::acceptJson()->connectTimeout(3)->timeout($config['timeout'])->get($config['url']);
                $activity = $response->successful() ? $response->json() : null;
            } catch (Throwable $e) {
                Log::info('Suggestions feed unavailable', ['error' => $e->getMessage()]);
                break;
            }

            if (! is_array($activity) || ! is_string($activity['activity'] ?? null) || ! is_string($activity['type'] ?? null)) {
                break;
            }

            $category = $config['types'][$activity['type']] ?? null;
            if ($category === null || ($activity['kidFriendly'] ?? false) !== true) {
                continue;
            }

            $ideas[] = [
                'title' => $activity['activity'],
                'category' => $category,
                'minutes' => is_string($activity['duration'] ?? null) ? ($config['minutes'][$activity['duration']] ?? null) : null,
            ];
        }

        return $ideas;
    }
}
