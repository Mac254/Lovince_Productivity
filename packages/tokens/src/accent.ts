import { accentRamps, type AccentName } from './primitives/color.ts';

const mix = {
    50: 8,
    100: 16,
    200: 32,
    300: 64,
    400: 100,
    500: -18,
    600: -34,
    700: -48,
    800: -60,
    900: -70,
    950: -82,
} as const;

export const customAccentPattern = /^#[0-9a-f]{6}$/i;

/** The swatch colour of a named accent, the step the highlight uses. */
export const accentSwatch = (name: AccentName): string => accentRamps[name][400];

/** The accent ramp for any colour as `--focus-green-*` overrides, mixed toward white and black in OKLCH. */
export function accentRamp(color: string): Record<string, string> {
    return Object.fromEntries(
        Object.entries(mix).map(([step, amount]) => [
            `--focus-green-${step}`,
            amount === 100
                ? color
                : amount > 0
                  ? `color-mix(in oklch, ${color} ${amount}%, white)`
                  : `color-mix(in oklch, ${color} ${100 + amount}%, black)`,
        ]),
    );
}

/** True when dark text reads on the colour, by WCAG relative luminance. */
export function isLightColor(hex: string): boolean {
    const [r, g, b] = [1, 3, 5].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;
        return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    }) as [number, number, number];
    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.18;
}
