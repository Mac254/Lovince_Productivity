import { COVERS, type Cover, type SuggestionCategory, type TaskCover } from '@focus/types';
import { suggestionCategoryLabels } from '@/lib/labels';

const pexels: Record<Cover, number> = {
    'focus-1': 4884116,
    'focus-2': 8534373,
    'growth-1': 32516751,
    'growth-2': 7937426,
    'health-1': 12360284,
    'health-2': 34017272,
    'wellbeing-1': 10728472,
    'wellbeing-2': 14499187,
    'social-1': 36765306,
    'social-2': 5047021,
    'fun-1': 27260270,
    'fun-2': 13010353,
};

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
    `${category}-${(seed % 2) + 1}` as Cover;
