// @vitest-environment node
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import { spacingSteps } from '@focus/tokens';
import { describe, expect, it } from 'vitest';

/**
 * Enforces "components consume decisions, never make them" across the UI package
 * and the web app: no raw colours, no arbitrary values, no off-scale spacing,
 * and no Tailwind defaults the token theme has removed.
 */
const root = fileURLToPath(new URL('../../../../', import.meta.url));
const scanned = ['packages/ui/src', 'apps/web/src'];

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) return name === '__tests__' ? [] : files(path);
        return /\.(vue|ts)$/.test(name) ? [path] : [];
    });
}

const allowedSteps = new Set(spacingSteps.map(String));
const spacing =
    /(?<![\w-])-?(?:p|px|py|pt|pr|pb|pl|m|mx|my|mt|mr|mb|ml|gap|gap-x|gap-y|space-x|space-y|inset|inset-x|inset-y|top|right|bottom|left)-(\d+(?:\.\d+)?)(?![\w./])/g;
const rules: { name: string; pattern: RegExp }[] = [
    { name: 'raw hex colour', pattern: /(?<![\w&])#[0-9a-fA-F]{3,8}\b/g },
    { name: 'arbitrary value', pattern: /(?<![\w])[a-z-]+-\[[^\]]+\]/g },
    {
        name: 'removed Tailwind default',
        pattern:
            /(?<![\w-])(?:text-(?:xs|sm|base|lg|[2-9]?xl)|rounded-(?:xs|xl|[23]xl)|shadow-(?:sm|md|lg|xl|2xl)|(?:bg|text|border|ring|fill)-(?:white|black|(?:gray|zinc|slate|red|blue|green|yellow|violet|purple)-\d+))(?![\w-])/g,
    },
];

function stripStyleBlocks(source: string): string {
    return source.replace(/<style[\s\S]*?<\/style>/g, '');
}

describe('token drift', () => {
    const sources = scanned
        .flatMap((dir) => files(join(root, dir)))
        .map((path) => ({
            path: relative(root, path),
            source: stripStyleBlocks(readFileSync(path, 'utf8')),
        }));

    it.each(rules)('finds no $name', ({ pattern }) => {
        const hits = sources.flatMap(({ path, source }) =>
            [...source.matchAll(pattern)].map((m) => `${path}: ${m[0]}`),
        );
        expect(hits).toEqual([]);
    });

    it('uses only spacing steps on the 4px scale', () => {
        const hits = sources.flatMap(({ path, source }) =>
            [...source.matchAll(spacing)].filter((m) => !allowedSteps.has(m[1]!)).map((m) => `${path}: ${m[0]}`),
        );
        expect(hits).toEqual([]);
    });
});
