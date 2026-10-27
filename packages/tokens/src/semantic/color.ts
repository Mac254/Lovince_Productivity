import type { ramps } from '../primitives/color.ts';

type Ref = {
    [R in keyof typeof ramps]: `${R}.${Extract<keyof (typeof ramps)[R], number | string>}`;
}[keyof typeof ramps];

export type ColorValue = Ref | `raw:${string}`;

/**
 * Semantic roles: what each colour is for. Themes remap these and nothing else.
 * Every background role is paired with the foreground that may sit on it (`*-ink`).
 * The theme is dark: black page, near-black cards, charcoal raised surfaces, neon green accents.
 */
export const darkTheme = {
    canvas: 'neutral.950',
    surface: 'neutral.900',
    'surface-raised': 'neutral.850',
    'surface-overlay': 'neutral.900',
    /* Inverse: light surfaces on the dark page (selected chips, secondary buttons, toasts). */
    'surface-inverse': 'neutral.50',
    'surface-inverse-hover': 'neutral.0',

    /* App chrome (sidebar, timer and clock panels): black with its own text roles. */
    chrome: 'neutral.950',
    'chrome-hover': 'neutral.850',
    'chrome-border': 'neutral.800',
    'chrome-fg': 'neutral.0',
    'chrome-fg-muted': 'neutral.400',

    fg: 'neutral.50',
    'fg-muted': 'neutral.400',
    'fg-subtle': 'neutral.500',
    'fg-inverse': 'neutral.950',
    'fg-inverse-muted': 'neutral.600',

    border: 'neutral.800',
    'border-control': 'neutral.500',
    'border-strong': 'neutral.400',

    /* Accent: charcoal surfaces carrying neon text (palette "Cc"); green graphics. */
    accent: 'green.500',
    'accent-solid': 'neutral.850',
    'accent-solid-hover': 'neutral.800',
    'accent-ink': 'green.400',
    'accent-fg': 'green.400',

    /* Highlight: neon green with black text (palette "Aa") — primary actions and the featured card. */
    highlight: 'green.400',
    'highlight-hover': 'green.300',
    'highlight-ink': 'neutral.950',
    'highlight-fg': 'green.400',

    danger: 'red.400',
    'danger-hover': 'red.300',
    'danger-ink': 'neutral.950',
    'danger-fg': 'red.300',

    /* Status: tinted badge fill, hairline border and text, one set per state. Never reused for series. */
    'success-subtle': 'green.950',
    'success-border': 'green.800',
    'success-fg': 'green.300',
    'warning-subtle': 'amber.950',
    'warning-border': 'amber.800',
    'warning-fg': 'amber.300',
    'danger-subtle': 'red.950',
    'danger-border': 'red.800',
    'info-subtle': 'blue.950',
    'info-border': 'blue.800',
    'info-fg': 'blue.300',

    'focus-ring': 'green.400',
    selection: 'green.800',
    skeleton: 'neutral.850',
    scrim: 'raw:rgb(0 0 0 / 0.7)',

    /* Charts: the measure in green; context series in grey; logged work in blue. Never reused for status. */
    'chart-1': 'green.600',
    'chart-2': 'neutral.500',
    'chart-3': 'blue.400',
    'chart-grid': 'neutral.850',

    /* Activity heatmap: one hue, dark to bright (sequential on a dark surface). */
    'heat-0': 'neutral.850',
    'heat-1': 'green.900',
    'heat-2': 'green.700',
    'heat-3': 'green.500',
    'heat-4': 'green.300',

    'tone-green': 'tone.green',
    'tone-blue': 'tone.blue',
    'tone-amber': 'tone.amber',
    'tone-violet': 'tone.violet',
    'tone-rose': 'tone.rose',
    'tone-cyan': 'tone.cyan',
} as const satisfies Record<string, ColorValue>;

export type ColorRole = keyof typeof darkTheme;

/**
 * The light theme: off-white page, white cards, black text. Neon green stays the fill for primary
 * actions; green used as text or a thin mark darkens to stay readable. Chrome stays black in both.
 */
export const lightTheme = {
    canvas: 'neutral.50',
    surface: 'neutral.0',
    'surface-raised': 'neutral.75',
    'surface-overlay': 'neutral.0',
    'surface-inverse': 'neutral.900',
    'surface-inverse-hover': 'neutral.850',

    chrome: 'neutral.950',
    'chrome-hover': 'neutral.850',
    'chrome-border': 'neutral.800',
    'chrome-fg': 'neutral.0',
    'chrome-fg-muted': 'neutral.400',

    fg: 'neutral.900',
    'fg-muted': 'neutral.600',
    'fg-subtle': 'neutral.500',
    'fg-inverse': 'neutral.50',
    'fg-inverse-muted': 'neutral.400',

    border: 'neutral.200',
    'border-control': 'neutral.500',
    'border-strong': 'neutral.600',

    accent: 'green.700',
    'accent-solid': 'neutral.850',
    'accent-solid-hover': 'neutral.800',
    'accent-ink': 'green.400',
    'accent-fg': 'green.800',

    highlight: 'green.400',
    'highlight-hover': 'green.500',
    'highlight-ink': 'neutral.950',
    'highlight-fg': 'green.800',

    danger: 'red.600',
    'danger-hover': 'red.700',
    'danger-ink': 'neutral.0',
    'danger-fg': 'red.700',

    'success-subtle': 'green.50',
    'success-border': 'green.200',
    'success-fg': 'green.800',
    'warning-subtle': 'amber.50',
    'warning-border': 'amber.200',
    'warning-fg': 'amber.700',
    'danger-subtle': 'red.50',
    'danger-border': 'red.200',
    'info-subtle': 'blue.50',
    'info-border': 'blue.200',
    'info-fg': 'blue.700',

    'focus-ring': 'green.700',
    selection: 'green.200',
    skeleton: 'neutral.100',
    scrim: 'raw:rgb(0 0 0 / 0.45)',

    'chart-1': 'green.600',
    'chart-2': 'neutral.500',
    'chart-3': 'blue.700',
    'chart-grid': 'neutral.100',

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
} as const satisfies Record<ColorRole, ColorValue>;

export const themes = { dark: darkTheme, light: lightTheme } as const;
export const defaultTheme = 'dark' satisfies keyof typeof themes;

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
    ...(['green', 'blue', 'amber', 'violet', 'rose', 'cyan'] as const).map((tone) => ({
        fg: `tone-${tone}` as const,
        bg: 'surface' as const,
        min: 3,
    })),
    { fg: 'chart-1', bg: 'surface', min: 3 },
    { fg: 'chart-2', bg: 'surface', min: 3 },
    { fg: 'chart-3', bg: 'surface', min: 3 },
    { fg: 'fg-inverse', bg: 'surface-inverse', min: 4.5 },
    { fg: 'fg-inverse', bg: 'surface-inverse-hover', min: 4.5 },
    { fg: 'fg-inverse-muted', bg: 'surface-inverse', min: 4.5 },
    { fg: 'chrome-fg', bg: 'chrome', min: 4.5 },
    { fg: 'chrome-fg', bg: 'chrome-hover', min: 4.5 },
    { fg: 'chrome-fg-muted', bg: 'chrome', min: 4.5 },
    { fg: 'chrome-fg-muted', bg: 'chrome-hover', min: 4.5 },
    { fg: 'highlight', bg: 'chrome', min: 3 },
    { fg: 'focus-ring', bg: 'chrome', min: 3 },
    { fg: 'accent-ink', bg: 'accent-solid', min: 4.5 },
    { fg: 'highlight-ink', bg: 'highlight', min: 4.5 },
    { fg: 'highlight-ink', bg: 'highlight-hover', min: 4.5 },
    { fg: 'danger-ink', bg: 'danger', min: 4.5 },
    { fg: 'danger-ink', bg: 'danger-hover', min: 4.5 },
    ...(['success', 'warning', 'danger', 'info'] as const).flatMap((status) => [
        { fg: `${status}-fg` as const, bg: `${status}-subtle` as const, min: 4.5 },
        { fg: `${status}-fg` as const, bg: 'surface' as const, min: 4.5 },
    ]),
];
