import type { EmojiGroup, EmojiRecord } from '@focus/ui';

const catalogue: { key: string; label: string; entries: string }[] = [
    {
        key: 'mood',
        label: 'Mood',
        entries:
            '😀 grinning, 😊 smiling, 🙂 slightly_smiling, 😌 relieved, 😎 cool, 🤩 star_struck, 🥳 celebrating, 😍 heart_eyes, 🤔 thinking, 😐 neutral, 😴 sleepy, 🥱 yawning, 😮‍💨 exhaling, 😅 sweat_smile, 😬 grimacing, 😕 confused, 😢 crying, 😤 frustrated, 😡 angry, 🤯 mind_blown, 🥲 smiling_through_tears, 🫠 melting, 😇 halo, 🤒 unwell',
    },
    {
        key: 'gestures',
        label: 'Gestures',
        entries:
            '👍 thumbs_up, 👎 thumbs_down, 👏 clapping, 🙌 raised_hands, 🙏 thanks, 💪 strong, 👀 eyes, 🤝 handshake, ✌️ victory, 🤞 fingers_crossed, 👋 wave, 🫡 salute, ✋ stop, 👉 pointing_right, ☝️ pointing_up, 🫶 heart_hands',
    },
    {
        key: 'work',
        label: 'Work',
        entries:
            '✅ done, ☑️ checked, ❌ cross, ⏳ in_progress, ⏰ alarm, 📅 calendar, 📌 pin, 📎 paperclip, 📝 memo, 📚 books, 💡 idea, 🧠 brain, 🎯 target, 🚀 launch, 🐛 bug, 🔧 fix, 🛠️ tools, 🔥 on_fire, 🧪 experiment, 📈 growth, 📉 decline, 💻 laptop, ☕ coffee, 🏁 finish',
    },
    {
        key: 'life',
        label: 'Life',
        entries:
            '🏃 running, 🧘 meditating, 🚶 walking, 🏋️ lifting, 🚴 cycling, 🍎 apple, 🥗 salad, 🍕 pizza, 🍵 tea, 💧 water, 🛌 rest, 🎧 music, 🎮 games, 🎨 art, 📖 reading, ✈️ travel, 🏠 home, 👪 family, 🐶 dog, 🐱 cat, 🎉 party, 🎁 gift, 💰 money, 🛒 shopping',
    },
    {
        key: 'nature',
        label: 'Nature',
        entries:
            '☀️ sun, 🌤️ sun_behind_cloud, ☁️ cloud, 🌧️ rain, ⛈️ storm, ❄️ snow, 🌈 rainbow, 🌙 moon, ⭐ star, ✨ sparkles, 🌱 seedling, 🌿 herb, 🌸 blossom, 🌻 sunflower, 🍂 autumn, 🌊 wave, 🏔️ mountain, 🌍 earth',
    },
    {
        key: 'symbols',
        label: 'Symbols',
        entries:
            '❤️ heart, 🧡 orange_heart, 💛 yellow_heart, 💚 green_heart, 💙 blue_heart, 💜 purple_heart, 🖤 black_heart, 💔 broken_heart, ⚡ lightning, 💯 hundred, ❗ exclamation, ❓ question, ⚠️ warning, 🔒 locked, 🔑 key, 🏆 trophy, 🥇 first_place, 🎖️ medal, ➕ plus, ➖ minus, ➡️ right_arrow, 🔁 repeat, 🟢 green_circle, 🔴 red_circle',
    },
];

export const emojiGroups: EmojiGroup[] = catalogue.map(({ key, label }) => ({ key, label }));

export const emojis: EmojiRecord[] = catalogue.flatMap(({ key, entries }) =>
    entries.split(',').map((entry) => {
        const [emoji, name] = entry.trim().split(' ', 2) as [string, string];
        return { emoji, label: name.replace(/_/g, ' '), group: key };
    }),
);

export const recentEmoji = ref<readonly string[]>([]);
