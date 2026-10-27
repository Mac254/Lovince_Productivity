export const fontFamily = {
    sans: "'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    display: "'Outfit Variable', 'Inter Variable', ui-sans-serif, system-ui, sans-serif",
    /* Code-like surfaces: the journal editor and its status bar. */
    mono: "'JetBrains Mono Variable', ui-monospace, 'SFMono-Regular', Menlo, Consolas, monospace",
} as const;

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
