<?php

namespace App\Modules\Suggestions\Feeds;

use App\Modules\Suggestions\Contracts\IdeaFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls random activities from the keyless Bored API, one per request, over HTTPS only, without
 * following redirects and refusing any reply that is not small JSON. Only kid-friendly, free or cheap,
 * accessible activities that fit in a day are kept, and only the activity text, type and duration are
 * read; links and every other field are ignored. The first failed or malformed reply ends the pull.
 */
class BoredApi implements IdeaFeed
{
    private const MAX_BYTES = 2048;

    public function fetch(int $count): array
    {
        $config = config('suggestions.feed');
        if (! str_starts_with((string) $config['url'], 'https://')) {
            return [];
        }

        $ideas = [];
        for ($pull = 0; $pull < $count; $pull++) {
            $activity = $this->pull($config);
            if ($activity === null) {
                break;
            }

            if ($this->suitable($activity, $config)) {
                $ideas[] = [
                    'title' => $activity['activity'],
                    'category' => $config['types'][$activity['type']],
                    'minutes' => $config['minutes'][$activity['duration']],
                ];
            }
        }

        return $ideas;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function pull(array $config): ?array
    {
        try {
            $response = Http::acceptJson()->withoutRedirecting()->connectTimeout(3)->timeout($config['timeout'])->get($config['url']);
        } catch (Throwable $e) {
            Log::info('Suggestions feed unavailable', ['error' => class_basename($e)]);

            return null;
        }

        if (! $response->successful() || ! str_contains((string) $response->header('Content-Type'), 'application/json') || strlen($response->body()) > self::MAX_BYTES) {
            return null;
        }

        $activity = json_decode($response->body(), true, 4);

        return is_array($activity) && is_string($activity['activity'] ?? null) ? $activity : null;
    }

    /**
     * @param  array<string, mixed>  $activity
     * @param  array<string, mixed>  $config
     */
    private function suitable(array $activity, array $config): bool
    {
        return is_string($activity['type'] ?? null) && isset($config['types'][$activity['type']])
            && is_string($activity['duration'] ?? null) && array_key_exists($activity['duration'], $config['minutes'])
            && ($activity['kidFriendly'] ?? null) === true
            && is_numeric($activity['price'] ?? null) && $activity['price'] <= $config['max_price']
            && is_string($activity['accessibility'] ?? null) && ! str_contains($activity['accessibility'], 'Major');
    }
}
