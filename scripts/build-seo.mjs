import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { render } from '../.bootstrap/seo-renderer/seo-render.js';
const { version } = JSON.parse(await readFile(new URL('../package.json', import.meta.url), 'utf8'));
const out = new URL('../public/build/seo/', import.meta.url);
const vite = JSON.parse(await readFile(new URL('../public/build/manifest.json', import.meta.url), 'utf8'));
const styles = new Set();
const visited = new Set(['resources/js/app.ts']); // Its CSS is already linked by @vite.
function collectStyles(key) {
    if (visited.has(key)) return;
    visited.add(key);
    const entry = vite[key];
    if (!entry) throw new Error(`Missing landing asset entry: ${key}`);
    for (const file of entry.css ?? []) styles.add(file);
    for (const key of entry.imports ?? []) collectStyles(key);
}
collectStyles('resources/js/pages/Welcome.vue');
await mkdir(out, { recursive: true });
for (const locale of ['ro', 'en']) {
    for (const member of [false, true]) {
        const html = await render(locale, member, version);
        if (!html.includes('<h1') || !html.includes('GloriaFood') || html.includes('data-page="app"')) throw new Error('Incomplete landing prerender.');
        await writeFile(new URL(`${locale}-${member ? 'member' : 'guest'}.html`, out), html);
    }
}
await writeFile(new URL('manifest.json', out), JSON.stringify({ version, styles: [...styles] }));
console.log('Generated Romanian/English landing HTML, guest and member variants.');
