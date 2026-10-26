export type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive';
export type ButtonSize = 'sm' | 'md' | 'lg';

/** Shared by Button and IconButton so both have one interaction feel. */
export const buttonVariants: Record<ButtonVariant, string> = {
    primary: 'bg-highlight text-highlight-ink hover:bg-highlight-hover',
    secondary: 'bg-surface-inverse text-fg-inverse hover:bg-surface-inverse-hover',
    outline: 'border border-border-control text-fg hover:border-border-strong hover:bg-surface',
    ghost: 'text-fg-muted hover:bg-surface hover:text-fg',
    destructive: 'bg-danger text-danger-ink hover:bg-danger-hover',
};

export const controlHeights: Record<ButtonSize, string> = {
    sm: 'h-(--control-sm)',
    md: 'h-(--control-md)',
    lg: 'h-(--control-lg)',
};

export const controlSquares: Record<ButtonSize, string> = {
    sm: 'size-(--control-sm)',
    md: 'size-(--control-md)',
    lg: 'size-(--control-lg)',
};

export const interactive =
    'group inline-flex shrink-0 items-center justify-center rounded-full font-semibold whitespace-nowrap select-none ' +
    'transition-colors duration-(--duration-instant) ease-out active:translate-y-px ' +
    'disabled:cursor-not-allowed disabled:opacity-50 aria-disabled:cursor-not-allowed aria-disabled:opacity-50';
