export const fontFamily = {
    sans: "'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    display: "'Outfit Variable', 'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    /* Code-like surfaces: the journal editor and its status bar. */
    mono: "'JetBrains Mono Variable', ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace",
    serif: "'Source Serif 4 Variable', ui-serif, Georgia, Cambria, serif",
    hand: "'Caveat Variable', 'Segoe Print', 'Bradley Hand', cursive",
} as const;

const families = {
    fraunces: "'Fraunces Variable', ui-serif, Georgia, serif",
    grotesk: "'Space Grotesk Variable', ui-sans-serif, system-ui, sans-serif",
    dmSans: "'DM Sans Variable', ui-sans-serif, system-ui, sans-serif",
    jakarta: "'Plus Jakarta Sans Variable', ui-sans-serif, system-ui, sans-serif",
    nunito: "'Nunito Variable', ui-rounded, ui-sans-serif, system-ui, sans-serif",
    sourceSans: "'Source Sans 3 Variable', ui-sans-serif, system-ui, sans-serif",
    system: "system-ui, -apple-system, 'Segoe UI', Roboto, ui-sans-serif, sans-serif",
} as const;

/** Heading and body typeface pairs, applied as `data-font` on the root. */
export const fontPairings = {
    focus: { display: fontFamily.display, sans: fontFamily.sans },
    editorial: { display: families.fraunces, sans: fontFamily.sans },
    modern: { display: families.grotesk, sans: families.dmSans },
    studio: { display: families.jakarta, sans: families.jakarta },
    classic: { display: fontFamily.serif, sans: families.sourceSans },
    friendly: { display: families.nunito, sans: families.nunito },
    system: { display: families.system, sans: families.system },
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
