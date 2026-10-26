import type { GoalTone } from '@focus/types';

/** Static class names so Tailwind can see them; tone colours are identity, never status. */
export const toneDot: Record<GoalTone, string> = {
    green: 'bg-tone-green',
    blue: 'bg-tone-blue',
    amber: 'bg-tone-amber',
    violet: 'bg-tone-violet',
    rose: 'bg-tone-rose',
    cyan: 'bg-tone-cyan',
};

export const toneBorder: Record<GoalTone, string> = {
    green: 'border-tone-green',
    blue: 'border-tone-blue',
    amber: 'border-tone-amber',
    violet: 'border-tone-violet',
    rose: 'border-tone-rose',
    cyan: 'border-tone-cyan',
};
