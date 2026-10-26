import { ramps, type RampName } from '../primitives/color.ts';
import type { ColorValue } from '../semantic/color.ts';

export function rampVar(ramp: string, step: string | number): string {
    return `--focus-${ramp}-${step}`;
}

/** Resolves a semantic reference (`violet.500`) to its hex, or returns a raw value. */
export function resolveColor(value: ColorValue): string {
    if (value.startsWith('raw:')) return value.slice(4);
    const [ramp, step] = value.split('.') as [RampName, string];
    const hex = (ramps[ramp] as Record<string, string>)[step];
    if (!hex) throw new Error(`Unknown colour reference ${value}`);
    return hex;
}

export function cssReference(value: ColorValue): string {
    if (value.startsWith('raw:')) return value.slice(4);
    const [ramp, step] = value.split('.');
    return `var(${rampVar(ramp!, step!)})`;
}
