export type ControlSurface = 'default' | 'chrome';

/**
 * Shared look for form controls: default · hover · focus · error · disabled. Text fields add their own `read-only:` look, since buttons such as the select trigger also match `:read-only`.
 * `chrome` is for controls on the always-dark chrome panels (the focus timer).
 */
export const controlClasses = (invalid = false, surface: ControlSurface = 'default') => [
    'w-full rounded-md border px-4 text-body placeholder:text-fg-subtle',
    surface === 'chrome' ? 'bg-chrome-hover text-chrome-fg' : 'bg-surface text-fg',
    'transition-colors duration-(--duration-instant) hover:border-border-strong',
    'focus-visible:border-highlight focus-visible:outline-none',
    'disabled:cursor-not-allowed disabled:opacity-50',
    invalid ? 'border-danger-fg' : surface === 'chrome' ? 'border-chrome-border' : 'border-border-control',
];
