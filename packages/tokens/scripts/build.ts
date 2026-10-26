import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { renderTailwindCss, renderVariablesCss } from '../src/build/render.ts';

const out = (file: string) => fileURLToPath(new URL(`../css/${file}`, import.meta.url));

writeFileSync(out('variables.css'), renderVariablesCss());
writeFileSync(out('tailwind.css'), renderTailwindCss());
console.log('Wrote packages/tokens/css/variables.css and tailwind.css');
