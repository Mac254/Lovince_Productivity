export const fontFamily = {
    sans: "'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    display: "'Outfit Variable', 'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    /* Code-like surfaces: the journal editor and its status bar. */
    mono: "'JetBrains Mono Variable', ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace",
    serif: "'Source Serif 4 Variable', ui-serif, Georgia, Cambria, serif",
    hand: "'Caveat Variable', 'Segoe Print', 'Bradley Hand', cursive",
} as const;

const fallback = {
    sans: 'ui-sans-serif, system-ui, sans-serif',
    serif: 'ui-serif, Georgia, Cambria, serif',
    rounded: 'ui-rounded, ui-sans-serif, system-ui, sans-serif',
    mono: "ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace",
} as const;

const face = (name: string, kind: keyof typeof fallback) => `'${name} Variable', ${fallback[kind]}`;

const families = {
    inter: fontFamily.sans,
    interTight: face('Inter Tight', 'sans'),
    fraunces: face('Fraunces', 'serif'),
    grotesk: face('Space Grotesk', 'sans'),
    dmSans: face('DM Sans', 'sans'),
    jakarta: face('Plus Jakarta Sans', 'sans'),
    nunito: face('Nunito', 'rounded'),
    sourceSans: face('Source Sans 3', 'sans'),
    geist: face('Geist', 'sans'),
    manrope: face('Manrope', 'sans'),
    figtree: face('Figtree', 'sans'),
    onest: face('Onest', 'sans'),
    publicSans: face('Public Sans', 'sans'),
    plex: face('IBM Plex Sans', 'sans'),
    workSans: face('Work Sans', 'sans'),
    lexend: face('Lexend', 'sans'),
    albert: face('Albert Sans', 'sans'),
    hanken: face('Hanken Grotesk', 'sans'),
    instrument: face('Instrument Sans', 'sans'),
    mona: face('Mona Sans', 'sans'),
    sora: face('Sora', 'sans'),
    archivo: face('Archivo', 'sans'),
    karla: face('Karla', 'sans'),
    montserrat: face('Montserrat', 'sans'),
    openSans: face('Open Sans', 'sans'),
    roboto: face('Roboto', 'sans'),
    atkinson: face('Atkinson Hyperlegible Next', 'sans'),
    newsreader: face('Newsreader', 'serif'),
    literata: face('Literata', 'serif'),
    lora: face('Lora', 'serif'),
    merriweather: face('Merriweather', 'serif'),
    playfair: face('Playfair Display', 'serif'),
    garamond: face('EB Garamond', 'serif'),
    crimson: face('Crimson Pro', 'serif'),
    bitter: face('Bitter', 'serif'),
    robotoSerif: face('Roboto Serif', 'serif'),
    quicksand: face('Quicksand', 'rounded'),
    comfortaa: face('Comfortaa', 'rounded'),
    baloo: face('Baloo 2', 'rounded'),
    fredoka: face('Fredoka', 'rounded'),
    rubik: face('Rubik', 'rounded'),
    jetbrains: fontFamily.mono,
    geistMono: face('Geist Mono', 'mono'),
    firaCode: face('Fira Code', 'mono'),
    sourceCode: face('Source Code Pro', 'mono'),
    robotoMono: face('Roboto Mono', 'mono'),
    redHatMono: face('Red Hat Mono', 'mono'),
    syne: face('Syne', 'sans'),
    unbounded: face('Unbounded', 'sans'),
    bricolage: face('Bricolage Grotesque', 'sans'),
    system: "system-ui, -apple-system, 'Segoe UI', Roboto, ui-sans-serif, sans-serif",
} as const;

const pair = (display: string, sans: string = display) => ({ display, sans });

/** Heading and body typeface pairs, applied as `data-font` on the root. */
export const fontPairings = {
    focus: pair(fontFamily.display, fontFamily.sans),
    modern: pair(families.grotesk, families.dmSans),
    studio: pair(families.jakarta),
    inter: pair(families.inter),
    inter_tight: pair(families.interTight, families.inter),
    geist: pair(families.geist),
    manrope: pair(families.manrope),
    figtree: pair(families.figtree),
    onest: pair(families.onest),
    public_sans: pair(families.publicSans),
    plex: pair(families.plex),
    work_sans: pair(families.workSans),
    lexend: pair(families.lexend),
    albert: pair(families.albert),
    hanken: pair(families.hanken),
    instrument: pair(families.instrument),
    mona: pair(families.mona),
    sora: pair(families.sora, families.inter),
    archivo: pair(families.archivo),
    karla: pair(families.karla),
    montserrat: pair(families.montserrat, families.openSans),
    open_sans: pair(families.openSans),
    roboto: pair(families.roboto),
    atkinson: pair(families.atkinson),
    editorial: pair(families.fraunces, families.inter),
    classic: pair(fontFamily.serif, families.sourceSans),
    newsreader: pair(families.newsreader, families.inter),
    literata: pair(families.literata),
    lora: pair(families.lora, families.workSans),
    merriweather: pair(families.merriweather, families.openSans),
    playfair: pair(families.playfair, families.inter),
    garamond: pair(families.garamond),
    crimson: pair(families.crimson, families.karla),
    bitter: pair(families.bitter),
    roboto_serif: pair(families.robotoSerif, families.roboto),
    friendly: pair(families.nunito),
    quicksand: pair(families.quicksand),
    comfortaa: pair(families.comfortaa, families.nunito),
    baloo: pair(families.baloo),
    fredoka: pair(families.fredoka, families.nunito),
    rubik: pair(families.rubik),
    jetbrains: pair(families.jetbrains),
    geist_mono: pair(families.geistMono, families.geist),
    fira_code: pair(families.firaCode),
    source_code: pair(families.sourceCode, families.sourceSans),
    roboto_mono: pair(families.robotoMono, families.roboto),
    red_hat_mono: pair(families.redHatMono),
    grotesk: pair(families.grotesk, families.inter),
    syne: pair(families.syne, families.inter),
    unbounded: pair(families.unbounded, families.inter),
    bricolage: pair(families.bricolage, families.inter),
    system: pair(families.system),
} as const;

export type FontPairing = keyof typeof fontPairings;

/** Weight 500 is the floor in this system; only these weights are used. */
export const fontWeight = {
    medium: 500,
    semibold: 600,
    bold: 700,
} as const;

/**
 * Type scale addressed by role. Every size is paired with its line height.
 * Display roles are for page titles and statistics only.
 */
export const textRoles = {
    caption: { size: '0.75rem', lineHeight: '1rem' },
    label: { size: '0.875rem', lineHeight: '1.25rem' },
    body: { size: '0.9375rem', lineHeight: '1.5rem' },
    lead: { size: '1.125rem', lineHeight: '1.75rem' },
    title: { size: '1.25rem', lineHeight: '1.75rem' },
    heading: { size: '1.5rem', lineHeight: '2rem' },
    'display-sm': { size: '3rem', lineHeight: '1' },
    'display-md': { size: '3.75rem', lineHeight: '1' },
    'display-lg': { size: '4.5rem', lineHeight: '1' },
} as const;

export type TextRole = keyof typeof textRoles;

export const tracking = {
    tight: '-0.02em',
    normal: '0',
} as const;

export const textScales = { small: '93.75%', default: '100%', large: '106.25%', larger: '112.5%' } as const;
