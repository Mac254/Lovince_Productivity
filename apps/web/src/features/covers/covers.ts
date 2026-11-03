import { COVERS, type Cover, type SuggestionCategory, type TaskCover } from '@focus/types';
import { suggestionCategoryLabels } from '@/lib/labels';

const pexels: Record<Cover, number> = {
    'focus-1': 4884116,
    'focus-2': 8534373,
    'focus-3': 8092507,
    'focus-4': 669228,
    'growth-1': 32516751,
    'growth-2': 7937426,
    'growth-3': 1251026,
    'growth-4': 17019160,
    'health-1': 12360284,
    'health-2': 34017272,
    'health-3': 30529077,
    'health-4': 566564,
    'wellbeing-1': 10728472,
    'wellbeing-2': 14499187,
    'wellbeing-3': 210288,
    'wellbeing-4': 13208207,
    'social-1': 36765306,
    'social-2': 5047021,
    'social-3': 35652755,
    'social-4': 5506091,
    'fun-1': 27260270,
    'fun-2': 13010353,
    'fun-3': 4618591,
    'fun-4': 28897042,
};

const COVERS_PER_CATEGORY = 4;

export const coverUrl = (key: Cover): string => `${import.meta.env.BASE_URL}covers/${key}.jpg`;

export const coverCredit = (key: Cover): string => `https://www.pexels.com/photo/${pexels[key]}/`;

export const coverOptions = COVERS.map((key) => {
    const [category, index] = key.split('-') as [SuggestionCategory, string];
    return { key, url: coverUrl(key), label: `${suggestionCategoryLabels[category]} photo ${index}` };
});

export function taskCoverUrl(cover: TaskCover | null | undefined): string | null {
    if (!cover) return null;
    return cover.type === 'upload' ? cover.url : coverUrl(cover.key);
}

export const categoryCover = (category: SuggestionCategory, seed: number): Cover =>
    `${category}-${(seed % COVERS_PER_CATEGORY) + 1}` as Cover;
