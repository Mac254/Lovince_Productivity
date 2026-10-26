/** Base unit 4px. Spacing utilities may only use these steps (Tailwind's ×4px multiplier). */
export const spacingBase = '0.25rem';
export const spacingSteps = [0, 1, 2, 3, 4, 6, 8, 12, 16, 20, 24, 32] as const;

/** One radius family: soft and rounded, matching the notched-card language. */
export const radius = {
    sm: '0.75rem',
    md: '1rem',
    lg: '1.75rem',
    full: '9999px',
} as const;

/** Dark UI: lightness carries elevation; shadows only separate floating layers. */
export const shadow = {
    card: '0 1px 2px rgb(0 0 0 / 0.05), 0 4px 16px -4px rgb(0 0 0 / 0.06)',
    floating: '0 1px 2px rgb(0 0 0 / 0.3), 0 12px 32px rgb(0 0 0 / 0.45)',
    overlay: '0 2px 4px rgb(0 0 0 / 0.35), 0 24px 64px rgb(0 0 0 / 0.55)',
} as const;

export const zIndex = {
    nav: 100,
    dropdown: 1000,
    sticky: 1100,
    overlay: 1200,
    modal: 1300,
    toast: 1400,
    skip: 1500,
} as const;

export const container = {
    app: '90rem',
    form: '28rem',
    dialog: '36rem',
} as const;
