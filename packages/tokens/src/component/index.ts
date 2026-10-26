/**
 * Component tokens: decisions specific to one component family.
 * Values reference semantic roles or scale primitives, never ramps.
 */
export const componentTokens = {
    'control-sm': '2.25rem',
    'control-md': '2.75rem',
    'control-lg': '3.5rem',
    'icon-sm': '1rem',
    'icon-md': '1.25rem',
    'icon-lg': '1.5rem',
    'notch-radius': '1.25rem',
    'notch-corner': '1.75rem',
    'sidebar-width': '16rem',
    'topbar-height': '4.5rem',
    'dialog-width': 'calc(100% - 2rem)',
    'dialog-max-height': 'calc(100dvh - 2rem)',
} as const;
