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
        200: '#b3ffca',
        300: '#66ff94',
        400: '#00ff4e',
        500: '#00d641',
        600: '#00a834',
        700: '#008a2b',
        800: '#006b21',
        900: '#004d18',
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
        200: '#fecaca',
        300: '#fca5a5',
        400: '#f87171',
        500: '#ef4444',
        600: '#dc2626',
        700: '#b91c1c',
    },
} as const;

export type RampName = keyof typeof ramps;
