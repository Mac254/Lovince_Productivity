import type { Settings } from '@focus/types';
import { loadFaces, type Face } from '@/features/settings/fonts';

export type JournalFont = Settings['journal']['font'];
export type JournalFontGroup = 'serif' | 'sans' | 'mono' | 'hand';

export const journalFontGroups: { value: JournalFontGroup; label: string }[] = [
    { value: 'serif', label: 'Serif' },
    { value: 'sans', label: 'Sans' },
    { value: 'mono', label: 'Mono' },
    { value: 'hand', label: 'Handwritten' },
];

const sizes: Record<JournalFontGroup, string> = {
    serif: 'text-lead',
    sans: 'text-body',
    mono: 'text-label',
    hand: 'text-title',
};

const font = (name: string, group: JournalFontGroup, ...faces: Face[]) => ({ name, group, size: sizes[group], faces });

export const journalFonts: Record<JournalFont, { name: string; group: JournalFontGroup; size: string; faces: Face[] }> =
    {
        serif: font('Source Serif', 'serif'),
        literata: font('Literata', 'serif', 'literata'),
        newsreader: font('Newsreader', 'serif', 'newsreader'),
        lora: font('Lora', 'serif', 'lora'),
        garamond: font('EB Garamond', 'serif', 'garamond'),
        merriweather: font('Merriweather', 'serif', 'merriweather'),
        crimson: font('Crimson Pro', 'serif', 'crimson'),
        sans: font('App font', 'sans'),
        atkinson: font('Atkinson Hyperlegible', 'sans', 'atkinson'),
        lexend: font('Lexend', 'sans', 'lexend'),
        quicksand: font('Quicksand', 'sans', 'quicksand'),
        nunito: font('Nunito', 'sans', 'nunito'),
        mono: font('JetBrains Mono', 'mono'),
        fira_code: font('Fira Code', 'mono', 'firaCode'),
        geist_mono: font('Geist Mono', 'mono', 'geistMono'),
        source_code: font('Source Code Pro', 'mono', 'sourceCode'),
        courier: font('Courier Prime', 'mono', 'courierPrime'),
        typewriter: font('Special Elite', 'mono', 'specialElite'),
        hand: font('Caveat', 'hand'),
        patrick_hand: font('Patrick Hand', 'hand', 'patrickHand'),
        kalam: font('Kalam', 'hand', 'kalam'),
        indie_flower: font('Indie Flower', 'hand', 'indieFlower'),
        dancing: font('Dancing Script', 'hand', 'dancing'),
        architects: font('Architects Daughter', 'hand', 'architects'),
    };

export function loadJournalFont(key: JournalFont): Promise<void> {
    return loadFaces(journalFonts[key]?.faces ?? []);
}
