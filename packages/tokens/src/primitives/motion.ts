/** Durations in milliseconds. Hover/focus use instant; entrances slow; nothing is slower than 500. */
export const duration = {
    instant: 100,
    fast: 150,
    normal: 200,
    slow: 300,
    slower: 500,
} as const;

/** Each easing is given in CSS form and as its closest GSAP equivalent. */
export const easing = {
    out: { css: 'cubic-bezier(0.16, 1, 0.3, 1)', gsap: 'expo.out' },
    in: { css: 'cubic-bezier(0.7, 0, 0.84, 0)', gsap: 'expo.in' },
    'in-out': { css: 'cubic-bezier(0.65, 0, 0.35, 1)', gsap: 'power3.inOut' },
} as const;

/** Entrance distance in px for lifted content. */
export const distance = {
    sm: 8,
    md: 16,
} as const;
