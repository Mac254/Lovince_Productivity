/**
 * Primitive colour ramps. Raw values with no meaning — only semantic roles may reference them.
 * Brand anchors: neon green #00ff4e (green 400), charcoal #242424 (neutral 850), black (neutral 950),
 * on black. Neutrals are true greys so the green is the only colour in the room.
 */
export const ramps = {
    neutral: {
        0: '#ffffff',
        50: '#f6f6f6',
        75: '#eef0ec',
        100: '#ececec',
        200: '#dcdcdc',
        300: '#bdbdbd',
        400: '#9a9a9a',
        500: '#767676',
        600: '#5c5c5c',
        700: '#3d3d3d',
        800: '#2e2e2e',
        850: '#242424',
        900: '#171717',
        950: '#000000',
    },
    green: {
        50: '#ecfff2',
        100: '#d4fbe0',
        200: '#b3ffca',
        300: '#66ff94',
        400: '#00ff4e',
        500: '#00d641',
        600: '#00a834',
        700: '#008a2b',
        800: '#006b21',
        900: '#004d18',
        950: '#03230d',
    },
    /* Job tones: identity colours for goals, validated as one categorical palette. */
    tone: {
        green: '#16a34a',
        blue: '#3b82f6',
        amber: '#d97706',
        violet: '#8b5cf6',
        rose: '#f43f5e',
        cyan: '#0891b2',
    },
    red: {
        50: '#fef2f2',
        200: '#fecaca',
        300: '#fca5a5',
        400: '#f87171',
        500: '#ef4444',
        600: '#dc2626',
        700: '#b91c1c',
        800: '#991b1b',
        950: '#2a0a0a',
    },
    /* Status ramps: blue (info) and amber (warning), stepped for tinted badges on black or white. */
    blue: {
        50: '#eff6ff',
        200: '#bfdbfe',
        300: '#93c5fd',
        400: '#60a5fa',
        700: '#1d4ed8',
        800: '#1e40af',
        950: '#0c1a38',
    },
    amber: {
        50: '#fffbeb',
        200: '#fde68a',
        300: '#fcd34d',
        400: '#fbbf24',
        700: '#b45309',
        800: '#92400e',
        950: '#2a1a05',
    },
} as const;

export type RampName = keyof typeof ramps;
