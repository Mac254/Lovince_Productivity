// Fails the build when the app grows past its size budget. Sizes are gzip, as a browser would
// download them. Raise a limit on purpose, in the same commit as the change that needs it.
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gzipSync } from 'node:zlib';

const KB = 1024;
const BUDGET = {
    firstLoad: 230 * KB, // everything the shell loads before any page is opened
    chunk: 200 * KB, // any single script, including lazy ones like the charts
    css: 60 * KB, // all stylesheets, including those of pages that load later
};

const out = fileURLToPath(new URL('../../api/public/app', import.meta.url));
const gzip = (file) => gzipSync(readFileSync(join(out, file)), { level: 9 }).length;
const kb = (bytes) => `${(bytes / KB).toFixed(0)} KB`;

const html = readFileSync(join(out, 'index.html'), 'utf8');
const firstLoad = new Set();
const queue = [...html.matchAll(/(?:src|href)="\/app\/(assets\/[^"]+\.(?:js|css))"/g)].map((m) => m[1]);
while (queue.length) {
    const file = queue.pop();
    if (firstLoad.has(file) || !existsSync(join(out, file))) continue;
    firstLoad.add(file);
    if (!file.endsWith('.js')) continue;
    const code = readFileSync(join(out, file), 'utf8');
    for (const [, dep] of code.matchAll(/(?:from|import)\s*"\.\/([^"]+\.js)"/g)) queue.push(`assets/${dep}`);
}

const assets = readdirSync(join(out, 'assets'));
const sized = (ext) => assets.filter((f) => f.endsWith(ext)).map((f) => [f, gzip(`assets/${f}`)]);
const scripts = sized('.js');
const css = sized('.css').reduce((sum, [, size]) => sum + size, 0);
const firstLoadSize = [...firstLoad].reduce((sum, file) => sum + gzip(file), 0);
const [largest, largestSize] = scripts.reduce((a, b) => (b[1] > a[1] ? b : a));

const checks = [
    [`First load (${firstLoad.size} files)`, firstLoadSize, BUDGET.firstLoad],
    [`Largest script (${largest})`, largestSize, BUDGET.chunk],
    ['All CSS', css, BUDGET.css],
];
let over = false;
for (const [label, size, limit] of checks) {
    const ok = size <= limit;
    over ||= !ok;
    console.log(`${ok ? '✓' : '✗'} ${label}: ${kb(size)} of ${kb(limit)}`);
}
if (over) {
    console.error('\nOver the size budget. Trim the bundle, or raise the limit in apps/web/scripts/size-budget.mjs.');
    process.exit(1);
}
