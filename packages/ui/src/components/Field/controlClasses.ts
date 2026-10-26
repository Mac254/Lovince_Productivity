/** Shared look for text-entry controls: default · hover · focus · error · disabled · readonly. */
export const controlClasses = (invalid = false) => [
    'w-full rounded-md border bg-surface px-4 text-body text-fg placeholder:text-fg-subtle',
    'transition-colors duration-(--duration-instant) hover:border-border-strong',
    'focus-visible:border-highlight focus-visible:outline-none',
    'disabled:cursor-not-allowed disabled:opacity-50 read-only:bg-canvas',
    invalid ? 'border-danger-fg' : 'border-border-control',
];
