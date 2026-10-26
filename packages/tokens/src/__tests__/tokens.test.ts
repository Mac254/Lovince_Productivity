// @vitest-environment node
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { renderTailwindCss, renderVariablesCss } from '../build/render.ts';
import { resolveColor } from '../build/resolve.ts';
import { contrastPairs, themes } from '../semantic/color.ts';

function luminance(hex: string): number {
    const channels = hex
        .replace('#', '')
        .match(/.{2}/g)!
        .map((part) => parseInt(part, 16) / 255)
        .map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    const [r, g, b] = channels as [number, number, number];
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contrast(a: string, b: string): number {
    const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x) as [number, number];
    return (hi + 0.05) / (lo + 0.05);
}

describe('design tokens', () => {
    it.each(Object.entries(themes))('meets every contrast minimum in the %s theme', (_, roles) => {
        const failures = contrastPairs
            .map(({ fg, bg, min }) => ({
                fg,
                bg,
                min,
                ratio: contrast(resolveColor(roles[fg]), resolveColor(roles[bg])),
            }))
            .filter(({ ratio, min }) => ratio < min)
            .map(({ fg, bg, min, ratio }) => `${fg} on ${bg}: ${ratio.toFixed(2)} < ${min}`);

        expect(failures).toEqual([]);
    });

    it('defines the same roles in every theme', () => {
        const [first, ...rest] = Object.values(themes).map((roles) => Object.keys(roles).sort());
        for (const roles of rest) expect(roles).toEqual(first);
    });

    it('keeps the committed CSS in sync with the token source', () => {
        const read = (file: string) =>
            readFileSync(fileURLToPath(new URL(`../../css/${file}`, import.meta.url)), 'utf8');
        expect(read('variables.css'), 'run `pnpm --filter @focus/tokens build`').toBe(renderVariablesCss());
        expect(read('tailwind.css'), 'run `pnpm --filter @focus/tokens build`').toBe(renderTailwindCss());
    });
});
