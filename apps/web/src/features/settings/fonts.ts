import type { Settings } from '@focus/types';

export type FontKey = Settings['appearance']['font'];
export type FontGroup = 'sans' | 'serif' | 'rounded' | 'mono' | 'display';

export const fontGroups: { value: FontGroup; label: string }[] = [
    { value: 'sans', label: 'Sans' },
    { value: 'serif', label: 'Serif' },
    { value: 'rounded', label: 'Rounded' },
    { value: 'mono', label: 'Mono' },
    { value: 'display', label: 'Display' },
];

const faces = {
    albertSans: () => import('@fontsource-variable/albert-sans'),
    archivo: () => import('@fontsource-variable/archivo'),
    atkinson: () => import('@fontsource-variable/atkinson-hyperlegible-next'),
    baloo: () => import('@fontsource-variable/baloo-2'),
    bitter: () => import('@fontsource-variable/bitter'),
    bricolage: () => import('@fontsource-variable/bricolage-grotesque'),
    comfortaa: () => import('@fontsource-variable/comfortaa'),
    crimson: () => import('@fontsource-variable/crimson-pro'),
    dmSans: () => import('@fontsource-variable/dm-sans'),
    garamond: () => import('@fontsource-variable/eb-garamond'),
    figtree: () => import('@fontsource-variable/figtree'),
    firaCode: () => import('@fontsource-variable/fira-code'),
    fraunces: () => import('@fontsource-variable/fraunces'),
    fredoka: () => import('@fontsource-variable/fredoka'),
    geist: () => import('@fontsource-variable/geist'),
    geistMono: () => import('@fontsource-variable/geist-mono'),
    hanken: () => import('@fontsource-variable/hanken-grotesk'),
    plex: () => import('@fontsource-variable/ibm-plex-sans'),
    instrument: () => import('@fontsource-variable/instrument-sans'),
    interTight: () => import('@fontsource-variable/inter-tight'),
    jakarta: () => import('@fontsource-variable/plus-jakarta-sans'),
    karla: () => import('@fontsource-variable/karla'),
    lexend: () => import('@fontsource-variable/lexend'),
    literata: () => import('@fontsource-variable/literata'),
    lora: () => import('@fontsource-variable/lora'),
    manrope: () => import('@fontsource-variable/manrope'),
    merriweather: () => import('@fontsource-variable/merriweather'),
    mona: () => import('@fontsource-variable/mona-sans'),
    montserrat: () => import('@fontsource-variable/montserrat'),
    newsreader: () => import('@fontsource-variable/newsreader'),
    nunito: () => import('@fontsource-variable/nunito'),
    onest: () => import('@fontsource-variable/onest'),
    openSans: () => import('@fontsource-variable/open-sans'),
    playfair: () => import('@fontsource-variable/playfair-display'),
    publicSans: () => import('@fontsource-variable/public-sans'),
    quicksand: () => import('@fontsource-variable/quicksand'),
    redHatMono: () => import('@fontsource-variable/red-hat-mono'),
    roboto: () => import('@fontsource-variable/roboto'),
    robotoMono: () => import('@fontsource-variable/roboto-mono'),
    robotoSerif: () => import('@fontsource-variable/roboto-serif'),
    rubik: () => import('@fontsource-variable/rubik'),
    sora: () => import('@fontsource-variable/sora'),
    sourceCode: () => import('@fontsource-variable/source-code-pro'),
    sourceSans: () => import('@fontsource-variable/source-sans-3'),
    spaceGrotesk: () => import('@fontsource-variable/space-grotesk'),
    syne: () => import('@fontsource-variable/syne'),
    unbounded: () => import('@fontsource-variable/unbounded'),
    workSans: () => import('@fontsource-variable/work-sans'),
} satisfies Record<string, () => Promise<unknown>>;

type Face = keyof typeof faces;

const font = (group: FontGroup, ...files: Face[]) => ({ group, faces: files });

export const fonts: Record<FontKey, { group: FontGroup; faces: Face[] }> = {
    focus: font('sans'),
    modern: font('sans', 'spaceGrotesk', 'dmSans'),
    studio: font('sans', 'jakarta'),
    inter: font('sans'),
    inter_tight: font('sans', 'interTight'),
    geist: font('sans', 'geist'),
    manrope: font('sans', 'manrope'),
    figtree: font('sans', 'figtree'),
    onest: font('sans', 'onest'),
    public_sans: font('sans', 'publicSans'),
    plex: font('sans', 'plex'),
    work_sans: font('sans', 'workSans'),
    lexend: font('sans', 'lexend'),
    albert: font('sans', 'albertSans'),
    hanken: font('sans', 'hanken'),
    instrument: font('sans', 'instrument'),
    mona: font('sans', 'mona'),
    sora: font('sans', 'sora'),
    archivo: font('sans', 'archivo'),
    karla: font('sans', 'karla'),
    montserrat: font('sans', 'montserrat', 'openSans'),
    open_sans: font('sans', 'openSans'),
    roboto: font('sans', 'roboto'),
    atkinson: font('sans', 'atkinson'),
    editorial: font('serif', 'fraunces'),
    classic: font('serif', 'sourceSans'),
    newsreader: font('serif', 'newsreader'),
    literata: font('serif', 'literata'),
    lora: font('serif', 'lora', 'workSans'),
    merriweather: font('serif', 'merriweather', 'openSans'),
    playfair: font('serif', 'playfair'),
    garamond: font('serif', 'garamond'),
    crimson: font('serif', 'crimson', 'karla'),
    bitter: font('serif', 'bitter'),
    roboto_serif: font('serif', 'robotoSerif', 'roboto'),
    friendly: font('rounded', 'nunito'),
    quicksand: font('rounded', 'quicksand'),
    comfortaa: font('rounded', 'comfortaa', 'nunito'),
    baloo: font('rounded', 'baloo'),
    fredoka: font('rounded', 'fredoka', 'nunito'),
    rubik: font('rounded', 'rubik'),
    jetbrains: font('mono'),
    geist_mono: font('mono', 'geistMono', 'geist'),
    fira_code: font('mono', 'firaCode'),
    source_code: font('mono', 'sourceCode', 'sourceSans'),
    roboto_mono: font('mono', 'robotoMono', 'roboto'),
    red_hat_mono: font('mono', 'redHatMono'),
    grotesk: font('display', 'spaceGrotesk'),
    syne: font('display', 'syne'),
    unbounded: font('display', 'unbounded'),
    bricolage: font('display', 'bricolage'),
    system: font('sans'),
};

const loading = new Map<Face, Promise<void>>();

function loadFace(face: Face): Promise<void> {
    let promise = loading.get(face);
    if (!promise) {
        promise = faces[face]().then(
            () => undefined,
            () => void loading.delete(face),
        );
        loading.set(face, promise);
    }
    return promise;
}

export function loadFont(key: string): Promise<void> {
    const entry = fonts[key as FontKey];
    return entry ? Promise.all(entry.faces.map(loadFace)).then(() => undefined) : Promise.resolve();
}
