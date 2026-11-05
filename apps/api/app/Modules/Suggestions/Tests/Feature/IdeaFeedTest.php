<?php

namespace App\Modules\Suggestions\Tests\Feature;

use App\Modules\Suggestions\Feeds\BoredApi;
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

    protected function setUp(): void
    {
        parent::setUp();
        config(['suggestions.feed.driver' => BoredApi::class]);
    }

    private function activity(string $activity, string $type = 'relaxation', array $extra = []): array
    {
        return $extra + ['activity' => $activity, 'type' => $type, 'participants' => 1, 'price' => 0, 'accessibility' => 'Few to no challenges', 'duration' => 'minutes', 'kidFriendly' => true, 'link' => '', 'key' => (string) crc32($activity)];
    }

    private function fetch(): void
    {
        $this->artisan('suggestions:fetch-ideas')->assertSuccessful();
    }

    public function test_the_job_stores_clean_ideas_from_the_feed(): void
    {
        Http::preventStrayRequests();
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('  Meditate   for five minutes. '))
            ->push($this->activity('Learn how to fold a paper crane', 'education', ['duration' => 'hours', 'link' => 'https://example.com']))
            ->push($this->activity('Text a friend you haven\'t talked to in a long time', 'social'))
            ->push($this->activity('Mystery activity', 'unknown'))
            ->push($this->activity('Learn calligraphy', 'education', ['duration' => 'weeks']))
            ->push($this->activity('Take a class at your local studio', 'education', ['kidFriendly' => false]))
            ->push($this->activity('Go see a show with friends', 'social', ['price' => 0.8]))
            ->push($this->activity('Learn how to iceskate', 'recreational', ['accessibility' => 'Major challenges'])),
        ]);
        config(['suggestions.feed.pulls' => 8]);

        $this->fetch();

        Http::assertSentCount(8);
        $ideas = PooledIdea::orderBy('id')->get();
        $this->assertSame(['Meditate for five minutes', 'Learn how to fold a paper crane', 'Text a friend you haven’t talked to in a long time'], $ideas->pluck('title')->all());
        $this->assertSame(['wellbeing', 'growth', 'social'], $ideas->pluck('category.value')->all());
        $this->assertSame([15, 60, 15], $ideas->pluck('minutes')->all());
        $this->assertSame('web-meditate-for-five-minutes', $ideas[0]->key);
        $this->assertSame('BoredApi', $ideas[0]->feed);
    }

    public function test_only_plain_sentence_case_ideas_with_an_approved_verb_and_no_blocked_word_get_in(): void
    {
        $rejected = [
            '<b>Learn</b> to fold a paper crane',
            'Learn &amp; play chess',
            'Visit https://example.com for deals',
            'Write a poem 🎉',
            'Learn Kotlin',
            'MEDITATE FOR FIVE MINUTES',
            'Learn',
            'Go stargazing',
            'Explore the nightlife of your city',
            'Go to a karaoke bar with some friends',
            'Take your dog on a walk',
            'Pull a harmless prank on one of your friends',
            'Find a charity and donate to it',
            'Learn about crypto trading',
            'Practice coding in your favorite lanaguage',
            'Write '.str_repeat('a very long ', 8).'note',
        ];
        $accepted = ['Learn and play a new card game', 'Do something nice for someone you care about'];
        $sequence = Http::sequence();
        foreach ([...$rejected, ...$accepted] as $activity) {
            $sequence->push($this->activity($activity, 'social'));
        }
        Http::fake([self::URL => $sequence]);
        config(['suggestions.feed.pulls' => count($rejected) + count($accepted)]);

        $this->fetch();

        $this->assertSame($accepted, PooledIdea::orderBy('id')->pluck('title')->all());
    }

    public function test_tightened_rules_clear_the_pool_and_withdraw_open_suggestions(): void
    {
        $user = User::factory()->create();
        PooledIdea::create(['key' => 'web-take-a-caffeine-nap', 'title' => 'Take a caffeine nap', 'category' => 'wellbeing', 'feed' => 'BoredApi']);
        PooledIdea::create(['key' => 'web-write-a-poem', 'title' => 'Write a poem', 'category' => 'fun', 'feed' => 'BoredApi']);
        $open = $user->suggestions()->create(['source' => 'library', 'key' => 'idea:web-take-a-caffeine-nap', 'title' => 'Take a caffeine nap', 'category' => 'wellbeing']);
        $done = $user->suggestions()->create(['source' => 'library', 'key' => 'idea:web-take-a-caffeine-nap:2026-11-01', 'title' => 'Take a caffeine nap', 'category' => 'wellbeing', 'status' => 'done']);
        Http::fake([self::URL => Http::response('', 503)]);

        $this->fetch();

        $this->assertSame(['Write a poem'], PooledIdea::pluck('title')->all());
        $this->assertModelMissing($open);
        $this->assertModelExists($done);
    }

    public function test_plain_http_feeds_are_never_called(): void
    {
        config(['suggestions.feed.url' => 'http://bored-api.appbrewery.com/random']);
        Http::preventStrayRequests();

        $this->fetch();

        Http::assertNothingSent();
    }

    public function test_redirects_are_not_followed(): void
    {
        Http::fake([
            self::URL => Http::response('', 302, ['Location' => 'https://elsewhere.example/random']),
            'elsewhere.example/*' => $this->activity('Write a poem'),
        ]);

        $this->fetch();

        Http::assertSentCount(1);
        $this->assertSame(0, PooledIdea::count());
    }

    public function test_oversized_replies_are_refused(): void
    {
        Http::fake([self::URL => $this->activity('Write a poem', 'recreational', ['padding' => str_repeat('x', 4096)])]);

        $this->fetch();

        Http::assertSentCount(1);
        $this->assertSame(0, PooledIdea::count());
    }

    public function test_replies_that_are_not_json_are_refused(): void
    {
        Http::fake([self::URL => Http::response(json_encode($this->activity('Write a song')), 200, ['Content-Type' => 'text/html'])]);

        $this->fetch();

        $this->assertSame(0, PooledIdea::count());
    }

    public function test_repeated_and_built_in_ideas_are_stored_once(): void
    {
        $builtIn = collect(config('suggestions.library'))->firstWhere('key', 'garden');
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Meditate for five minutes'))
            ->push($this->activity('meditate for five minutes!'))
            ->push($this->activity($builtIn['title'], 'recreational'))
            ->push($this->activity('Take a bubble bath'))
            ->push($this->activity('MEDITATE FOR FIVE MINUTES'))
            ->push($this->activity('Meditate for five minutes')),
        ]);

        $this->fetch();
        $this->assertSame(['Meditate for five minutes', 'Take a bubble bath'], PooledIdea::orderBy('id')->pluck('title')->all());

        Http::fake([self::URL => $this->activity('Take a bubble bath')]);
        $this->fetch();
        $this->assertSame(2, PooledIdea::count());
    }

    public function test_the_pool_keeps_only_the_newest_ideas(): void
    {
        config(['suggestions.feed.keep' => 3]);
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Write a poem'))->push($this->activity('Write a song'))->push($this->activity('Write a short story'))
            ->push($this->activity('Draw something interesting'))->push($this->activity('Do a jigsaw puzzle'))->push($this->activity('Watch a classic movie')),
        ]);

        $this->fetch();

        $this->assertSame(['Draw something interesting', 'Do a jigsaw puzzle', 'Watch a classic movie'], PooledIdea::orderBy('id')->pluck('title')->all());
    }

    public function test_failures_are_quiet_and_keep_what_was_already_fetched(): void
    {
        Http::fake([self::URL => Http::sequence()
            ->push($this->activity('Take a bubble bath'))
            ->push('Too many requests', 429)
            ->push($this->activity('Never reached')),
        ]);
        $this->fetch();
        $this->assertSame(['Take a bubble bath'], PooledIdea::pluck('title')->all());

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
        foreach (['Meditate for five minutes' => 'wellbeing', 'Take a bubble bath' => 'wellbeing', 'Learn origami' => 'growth'] as $title => $category) {
            PooledIdea::create(['key' => 'web-'.str($title)->slug(), 'title' => $title, 'category' => $category, 'minutes' => 15, 'feed' => 'BoredApi']);
        }

        $engine->refresh($user);
        $open = Suggestion::where('source', 'library')->orderBy('id')->get();
        $this->assertSame(4, $open->count());
        $this->assertEqualsCanonicalizing(['idea:garden', 'idea:web-meditate-for-five-minutes', 'idea:web-take-a-bubble-bath', 'idea:web-learn-origami'], $open->pluck('key')->all());
        $this->assertCount(3, $open->take(3)->pluck('category')->unique());
        $this->assertSame(['type' => 'task', 'minutes' => 15], $open->firstWhere('key', 'idea:web-learn-origami')->action);

        $open->firstWhere('key', 'idea:web-learn-origami')->update(['status' => 'dismissed']);
        PooledIdea::where('key', 'web-take-a-bubble-bath')->delete();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:00'));
        $engine->refresh($user);
        $this->assertSame(1, Suggestion::where('key', 'like', 'idea:web-learn-origami%')->count());
        $this->assertSame(0, Suggestion::where('status', 'new')->where('key', 'like', 'idea:web-take-a-bubble-bath%')->count());
    }
}
