<?php

namespace App\Modules\Suggestions\Tests\Feature;

use App\Modules\Suggestions\Models\PooledIdea;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Services\SuggestionEngine;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdeaFeedTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://bored-api.appbrewery.com/random';

    private function activity(string $activity, string $type = 'relaxation', array $extra = []): array
    {
        return $extra + ['activity' => $activity, 'type' => $type, 'participants' => 1, 'duration' => 'minutes', 'kidFriendly' => true, 'key' => (string) crc32($activity)];
    }

    private function fetch(): void
    {
        $this->artisan('suggestions:fetch-ideas')->assertSuccessful();
    }

    public function test_the_job_stores_clean_ideas_from_the_feed(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('  go   STARGAZING tonight. '))
            ->push($this->activity('<b>Learn</b> to fold a paper crane', 'education', ['duration' => 'hours']))
            ->push($this->activity('Text a friend you haven\'t talked to in a long time', 'social'))
            ->push($this->activity('Visit https://example.com for deals', 'recreational'))
            ->push($this->activity('Brew your own beer', 'cooking'))
            ->push($this->activity('Mystery activity', 'unknown')),
        ]);

        $this->fetch();

        Http::assertSentCount(6);
        $ideas = PooledIdea::orderBy('id')->get();
        $this->assertSame(['Go stargazing tonight', 'Learn to fold a paper crane', 'Text a friend you haven’t talked to in a long time'], $ideas->pluck('title')->all());
        $this->assertSame(['wellbeing', 'growth', 'social'], $ideas->pluck('category.value')->all());
        $this->assertSame([15, 60, 15], $ideas->pluck('minutes')->all());
        $this->assertSame('web-go-stargazing-tonight', $ideas[0]->key);
        $this->assertSame('BoredApi', $ideas[0]->feed);
    }

    public function test_repeated_and_built_in_ideas_are_stored_once(): void
    {
        $builtIn = collect(config('suggestions.library'))->firstWhere('key', 'garden');
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Meditate for five minutes'))
            ->push($this->activity('meditate for five minutes!'))
            ->push($this->activity($builtIn['title'], 'diy'))
            ->push($this->activity('Take a caffeine nap'))
            ->push($this->activity('MEDITATE FOR FIVE MINUTES'))
            ->push($this->activity('Meditate for five minutes')),
        ]);

        $this->fetch();
        $this->assertSame(['Meditate for five minutes', 'Take a caffeine nap'], PooledIdea::orderBy('id')->pluck('title')->all());

        Http::fake([self::URL => $this->activity('Take a caffeine nap')]);
        $this->fetch();
        $this->assertSame(2, PooledIdea::count());
    }

    public function test_the_pool_keeps_only_the_newest_ideas(): void
    {
        config(['suggestions.feed.keep' => 3]);
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Idea one'))->push($this->activity('Idea two'))->push($this->activity('Idea three'))
            ->push($this->activity('Idea four'))->push($this->activity('Idea five'))->push($this->activity('Idea six')),
        ]);

        $this->fetch();

        $this->assertSame(['Idea four', 'Idea five', 'Idea six'], PooledIdea::orderBy('id')->pluck('title')->all());
    }

    public function test_failures_are_quiet_and_keep_what_was_already_fetched(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Take your dog on a walk'))
            ->push('Too many requests', 429)
            ->push($this->activity('Never reached')),
        ]);
        $this->fetch();
        $this->assertSame(['Take your dog on a walk'], PooledIdea::pluck('title')->all());

        Http::fake([self::URL => fn () => throw new ConnectionException('timed out')]);
        $this->fetch();

        Http::fake([self::URL => Http::response('<html>Cannot GET</html>', 200)]);
        $this->fetch();

        Http::fake([self::URL => Http::response(['activity' => ['nested'], 'type' => 'social'])]);
        $this->fetch();

        $this->assertSame(1, PooledIdea::count());
    }

    public function test_the_feed_can_be_turned_off(): void
    {
        config(['suggestions.feed.enabled' => false]);
        Http::preventStrayRequests();

        $this->fetch();

        Http::assertNothingSent();
    }

    public function test_pooled_ideas_join_the_rotation_and_built_in_ideas_remain_the_fallback(): void
    {
        Http::preventStrayRequests();
        $this->travelTo(CarbonImmutable::parse('2026-11-05 09:00'));
        $user = User::factory()->create(['settings' => ['suggestions' => ['ideas_per_day' => '5']]]);
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user);
        $this->assertSame(0, Suggestion::where('key', 'like', 'idea:web-%')->count());
        $this->assertSame(5, Suggestion::where('source', 'library')->count());

        Suggestion::query()->delete();
        config(['suggestions.library' => [collect(config('suggestions.library'))->firstWhere('key', 'garden')]]);
        foreach (['Meditate for five minutes' => 'wellbeing', 'Take a caffeine nap' => 'wellbeing', 'Learn calligraphy' => 'growth'] as $title => $category) {
            PooledIdea::create(['key' => 'web-'.str($title)->slug(), 'title' => $title, 'category' => $category, 'minutes' => 15, 'feed' => 'BoredApi']);
        }

        $engine->refresh($user);
        $open = Suggestion::where('source', 'library')->orderBy('id')->get();
        $this->assertSame(4, $open->count());
        $this->assertEqualsCanonicalizing(['idea:garden', 'idea:web-meditate-for-five-minutes', 'idea:web-take-a-caffeine-nap', 'idea:web-learn-calligraphy'], $open->pluck('key')->all());
        $this->assertCount(3, $open->take(3)->pluck('category')->unique());
        $this->assertSame(['type' => 'task', 'minutes' => 15], $open->firstWhere('key', 'idea:web-learn-calligraphy')->action);

        $open->firstWhere('key', 'idea:web-learn-calligraphy')->update(['status' => 'dismissed']);
        PooledIdea::where('key', 'web-take-a-caffeine-nap')->delete();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00'));
        $engine->refresh($user);
        $this->assertSame(1, Suggestion::where('key', 'like', 'idea:web-learn-calligraphy%')->count());
        $this->assertSame(0, Suggestion::where('status', 'new')->where('key', 'like', 'idea:web-take-a-caffeine-nap%')->count());
    }
}
