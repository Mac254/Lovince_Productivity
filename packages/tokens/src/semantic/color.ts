import type { ramps } from '../primitives/color.ts';

type Ref = {
    [R in keyof typeof ramps]: `${R}.${Extract<keyof (typeof ramps)[R], number | string>}`;
}[keyof typeof ramps];

export type ColorValue = Ref | `raw:${string}`;

/**
 * Semantic roles: what each colour is for. Themes remap these and nothing else.
 * Every background role is paired with the foreground that may sit on it (`*-ink`).
 * The default theme is light: a white canvas with soft blue-grey surfaces.
 */
export const lightTheme = {
    /* Blended: a soft grey-green canvas, white cards, charcoal chrome. */
    canvas: 'neutral.75',
    surface: 'neutral.0',
    'surface-raised': 'neutral.50',
    'surface-overlay': 'neutral.0',
    'surface-inverse': 'neutral.850',
    'surface-inverse-hover': 'neutral.900',

    /* App chrome (sidebar, navigation drawer): pure black. */
    chrome: 'neutral.950',
    'chrome-hover': 'neutral.850',
    'chrome-border': 'neutral.800',

    fg: 'neutral.950',
    'fg-muted': 'neutral.600',
    'fg-subtle': 'neutral.500',
    'fg-inverse': 'neutral.0',
    'fg-inverse-muted': 'neutral.400',

    border: 'neutral.200',
    'border-control': 'neutral.500',
    'border-strong': 'neutral.700',

    /* Accent: black surfaces carrying neon text (palette "Cc"); green graphics on white. */
    accent: 'green.700',
    'accent-solid': 'neutral.950',
    'accent-solid-hover': 'neutral.850',
    'accent-ink': 'green.400',
    'accent-fg': 'green.800',

    /* Highlight: neon green with black text (palette "Aa") — primary actions and the featured card. */
    highlight: 'green.400',
    'highlight-hover': 'green.300',
    'highlight-ink': 'neutral.950',
    'highlight-fg': 'green.800',

    danger: 'red.600',
    'danger-hover': 'red.700',
    'danger-ink': 'neutral.0',
    'danger-fg': 'red.700',

    'focus-ring': 'green.700',
    selection: 'green.300',
    skeleton: 'neutral.100',
    scrim: 'raw:rgb(0 0 0 / 0.5)',

    /* Charts: the measure in green; context series in grey. Never reused for status. */
    'chart-1': 'green.700',
    'chart-2': 'neutral.500',
    'chart-grid': 'neutral.100',

    /* Activity heatmap: one hue, light to dark (sequential). */
    'heat-0': 'neutral.100',
    'heat-1': 'green.200',
    'heat-2': 'green.400',
    'heat-3': 'green.600',
    'heat-4': 'green.800',

    'tone-green': 'tone.green',
    'tone-blue': 'tone.blue',
    'tone-amber': 'tone.amber',
    'tone-violet': 'tone.violet',
    'tone-rose': 'tone.rose',
    'tone-cyan': 'tone.cyan',
} as const satisfies Record<string, ColorValue>;

export type ColorRole = keyof typeof lightTheme;

export const themes = { light: lightTheme } as const;
export const defaultTheme = 'light' satisfies keyof typeof themes;

/**
 * Pairings that must meet a contrast minimum in every theme:
 * 4.5 for text, 3 for large text, icons, control boundaries and the focus ring.
 */
export const contrastPairs: { fg: ColorRole; bg: ColorRole; min: number }[] = [
    ...(['canvas', 'surface', 'surface-raised', 'surface-overlay'] as const).flatMap((bg) => [
        { fg: 'fg' as const, bg, min: 4.5 },
        { fg: 'fg-muted' as const, bg, min: 4.5 },
        { fg: 'fg-subtle' as const, bg, min: 3 },
        { fg: 'accent-fg' as const, bg, min: 4.5 },
        { fg: 'highlight-fg' as const, bg, min: 4.5 },
        { fg: 'danger-fg' as const, bg, min: 4.5 },
        { fg: 'border-control' as const, bg, min: 3 },
        { fg: 'focus-ring' as const, bg, min: 3 },
        { fg: 'accent' as const, bg, min: 3 },
    ]),
    { fg: 'fg-inverse', bg: 'surface-inverse', min: 4.5 },
    { fg: 'fg-inverse-muted', bg: 'surface-inverse', min: 4.5 },
    { fg: 'fg-inverse-muted', bg: 'surface-inverse-hover', min: 4.5 },
    { fg: 'fg-inverse', bg: 'chrome', min: 4.5 },
    { fg: 'fg-inverse', bg: 'chrome-hover', min: 4.5 },
    { fg: 'fg-inverse-muted', bg: 'chrome', min: 4.5 },
    { fg: 'fg-inverse-muted', bg: 'chrome-hover', min: 4.5 },
    { fg: 'highlight', bg: 'chrome', min: 3 },
    { fg: 'focus-ring', bg: 'chrome', min: 3 },
    { fg: 'fg-inverse', bg: 'surface-inverse-hover', min: 4.5 },
    { fg: 'accent-ink', bg: 'accent-solid', min: 4.5 },
    { fg: 'highlight-ink', bg: 'highlight', min: 4.5 },
    { fg: 'highlight-ink', bg: 'highlight-hover', min: 4.5 },
    { fg: 'danger-ink', bg: 'danger', min: 4.5 },
    { fg: 'danger-ink', bg: 'danger-hover', min: 4.5 },
    ...(['green', 'blue', 'amber', 'violet', 'rose', 'cyan'] as const).map((tone) => ({
        fg: `tone-${tone}` as const,
        bg: 'surface' as const,
        min: 3,
    })),
    { fg: 'chart-1', bg: 'canvas', min: 3 },
    { fg: 'chart-2', bg: 'canvas', min: 3 },
    { fg: 'focus-ring', bg: 'surface-inverse', min: 3 },
    { fg: 'fg-inverse', bg: 'accent-solid', min: 4.5 },
];
