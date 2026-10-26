<?php

namespace Database\Seeders;

use App\Models\Quote;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    /**
     * Safe to run repeatedly: quotes are matched on their text.
     */
    public function run(): void
    {
        $now = now();

        $rows = array_map(fn (array $quote) => [
            'text' => $quote[0],
            'author' => $quote[1],
            'created_at' => $now,
            'updated_at' => $now,
        ], self::QUOTES);

        Quote::query()->upsert($rows, ['text'], ['author', 'updated_at']);
    }

    private const QUOTES = [
        ['We are what we repeatedly do. Excellence, then, is not an act, but a habit.', 'Will Durant'],
        ['It does not matter how slowly you go as long as you do not stop.', 'Confucius'],
        ['Simplicity is prerequisite for reliability.', 'Edsger W. Dijkstra'],
        ['Plans are worthless, but planning is everything.', 'Dwight D. Eisenhower'],
        ['The best way out is always through.', 'Robert Frost'],
        ['Well begun is half done.', 'Aristotle'],
        ['Focus is a matter of deciding what things you’re not going to do.', 'John Carmack'],
        ['Deciding what not to do is as important as deciding what to do.', 'Steve Jobs'],
        ['If you spend too much time thinking about a thing, you’ll never get it done.', 'Bruce Lee'],
        ['Make it work, make it right, make it fast.', 'Kent Beck'],
        ['First, solve the problem. Then, write the code.', 'John Johnson'],
        ['You do not rise to the level of your goals. You fall to the level of your systems.', 'James Clear'],
        ['The way to get started is to quit talking and begin doing.', 'Walt Disney'],
        ['Perfection is achieved, not when there is nothing more to add, but when there is nothing left to take away.', 'Antoine de Saint-Exupéry'],
        ['Nothing will work unless you do.', 'Maya Angelou'],
        ['The impediment to action advances action. What stands in the way becomes the way.', 'Marcus Aurelius'],
        ['Waste no more time arguing about what a good man should be. Be one.', 'Marcus Aurelius'],
        ['Energy and persistence conquer all things.', 'Benjamin Franklin'],
        ['Lost time is never found again.', 'Benjamin Franklin'],
        ['Small deeds done are better than great deeds planned.', 'Peter Marshall'],
        ['Programs must be written for people to read, and only incidentally for machines to execute.', 'Harold Abelson'],
        ['Talk is cheap. Show me the code.', 'Linus Torvalds'],
        ['Any fool can write code that a computer can understand. Good programmers write code that humans can understand.', 'Martin Fowler'],
        ['Efficiency is doing things right; effectiveness is doing the right things.', 'Peter Drucker'],
        ['Until we can manage time, we can manage nothing else.', 'Peter Drucker'],
        ['The man who moves a mountain begins by carrying away small stones.', 'Confucius'],
        ['Concentrate all your thoughts upon the work at hand. The sun’s rays do not burn until brought to a focus.', 'Alexander Graham Bell'],
        ['Absorb what is useful, discard what is useless and add what is specifically your own.', 'Bruce Lee'],
        ['Start where you are. Use what you have. Do what you can.', 'Arthur Ashe'],
        ['It always seems impossible until it’s done.', 'Nelson Mandela'],
        ['Either you run the day or the day runs you.', 'Jim Rohn'],
        ['Motivation is what gets you started. Habit is what keeps you going.', 'Jim Rohn'],
        ['Don’t watch the clock; do what it does. Keep going.', 'Sam Levenson'],
        ['Amateurs sit and wait for inspiration, the rest of us just get up and go to work.', 'Stephen King'],
        ['The secret of getting ahead is getting started.', null],
    ];
}
