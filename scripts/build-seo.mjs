import { mkdir, writeFile, readFile } from 'node:fs/promises';
import { render } from '../.bootstrap/seo-renderer/seo-render.js';
const { version } = JSON.parse(await readFile(new URL('../package.json', import.meta.url), 'utf8'));
const out = new URL('../public/build/seo/', import.meta.url);
await mkdir(out, { recursive: true });
for (const locale of ['ro', 'en']) {
    for (const member of [false, true]) {
        const html = await render(locale, member, version);
        if (!html.includes('<h1') || !html.includes('GloriaFood') || html.includes('data-page="app"')) throw new Error('Incomplete landing prerender.');
        await writeFile(new URL(`${locale}-${member ? 'member' : 'guest'}.html`, out), html);
    }
}
await writeFile(new URL('manifest.json', out), JSON.stringify({ version }));
console.log('Generated Romanian/English landing HTML, guest and member variants.');
