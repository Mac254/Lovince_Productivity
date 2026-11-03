export interface Emoji {
    char: string;
    name: string;
}

export interface EmojiGroup {
    value: string;
    label: string;
    emoji: Emoji[];
}

const list = (entries: string): Emoji[] =>
    entries.split(',').map((entry) => {
        const [char, name] = entry.trim().split(' ', 2) as [string, string];
        return { char, name: name.replace(/_/g, ' ') };
    });

export const emojiGroups: EmojiGroup[] = [
    {
        value: 'mood',
        label: 'Mood',
        emoji: list(
            '😀 grinning, 😊 smiling, 🙂 slightly_smiling, 😌 relieved, 😎 cool, 🤩 star_struck, 🥳 celebrating, 😍 heart_eyes, 🤔 thinking, 😐 neutral, 😴 sleepy, 🥱 yawning, 😮‍💨 exhaling, 😅 sweat_smile, 😬 grimacing, 😕 confused, 😢 crying, 😤 frustrated, 😡 angry, 🤯 mind_blown, 🥲 smiling_through_tears, 🫠 melting, 😇 halo, 🤒 unwell',
        ),
    },
    {
        value: 'gestures',
        label: 'Gestures',
        emoji: list(
            '👍 thumbs_up, 👎 thumbs_down, 👏 clapping, 🙌 raised_hands, 🙏 thanks, 💪 strong, 👀 eyes, 🤝 handshake, ✌️ victory, 🤞 fingers_crossed, 👋 wave, 🫡 salute, ✋ stop, 👉 pointing_right, ☝️ pointing_up, 🫶 heart_hands',
        ),
    },
    {
        value: 'work',
        label: 'Work',
        emoji: list(
            '✅ done, ☑️ checked, ❌ cross, ⏳ in_progress, ⏰ alarm, 📅 calendar, 📌 pin, 📎 paperclip, 📝 memo, 📚 books, 💡 idea, 🧠 brain, 🎯 target, 🚀 launch, 🐛 bug, 🔧 fix, 🛠️ tools, 🔥 on_fire, 🧪 experiment, 📈 growth, 📉 decline, 💻 laptop, ☕ coffee, 🏁 finish',
        ),
    },
    {
        value: 'life',
        label: 'Life',
        emoji: list(
            '🏃 running, 🧘 meditating, 🚶 walking, 🏋️ lifting, 🚴 cycling, 🍎 apple, 🥗 salad, 🍕 pizza, 🍵 tea, 💧 water, 🛌 rest, 🎧 music, 🎮 games, 🎨 art, 📖 reading, ✈️ travel, 🏠 home, 👪 family, 🐶 dog, 🐱 cat, 🎉 party, 🎁 gift, 💰 money, 🛒 shopping',
        ),
    },
    {
        value: 'nature',
        label: 'Nature',
        emoji: list(
            '☀️ sun, 🌤️ sun_behind_cloud, ☁️ cloud, 🌧️ rain, ⛈️ storm, ❄️ snow, 🌈 rainbow, 🌙 moon, ⭐ star, ✨ sparkles, 🌱 seedling, 🌿 herb, 🌸 blossom, 🌻 sunflower, 🍂 autumn, 🌊 wave, 🏔️ mountain, 🌍 earth',
        ),
    },
    {
        value: 'symbols',
        label: 'Symbols',
        emoji: list(
            '❤️ heart, 🧡 orange_heart, 💛 yellow_heart, 💚 green_heart, 💙 blue_heart, 💜 purple_heart, 🖤 black_heart, 💔 broken_heart, ⚡ lightning, 💯 hundred, ❗ exclamation, ❓ question, ⚠️ warning, 🔒 locked, 🔑 key, 🏆 trophy, 🥇 first_place, 🎖️ medal, ➕ plus, ➖ minus, ➡️ right_arrow, 🔁 repeat, 🟢 green_circle, 🔴 red_circle',
        ),
    },
];
