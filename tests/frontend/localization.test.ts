import { afterEach, expect, it, vi } from 'vitest';
import { computed } from 'vue';
import { readFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';
import { i18n, tr } from '../../resources/js/i18n';
import en from '../../resources/js/i18n/en.json';
import ro from '../../resources/js/i18n/ro.json';
import { api } from '../../resources/js/lib/http';
afterEach(() => { i18n.global.locale.value = 'ro'; vi.unstubAllGlobals(); });
it('defaults to Romanian and switches existing reactive labels to English', () => {
    const label = computed(() => tr('a_fresh_day_a_clear_view'));
    expect(i18n.global.locale.value).toBe('ro'); expect(label.value).toBe('O zi nouă. O imagine clară.');
    i18n.global.locale.value = 'en'; expect(label.value).toBe('A fresh day. A clear view.');
});
it('keeps complete catalogues with valid Romanian text and defined static UI keys', () => {
    expect(Object.keys(ro).sort()).toEqual(Object.keys(en).sort());
    expect(JSON.stringify(ro)).not.toMatch(/Ã|Â|â€|\uFFFD/);
    function scan(directory: string) { for (const entry of readdirSync(directory, { withFileTypes: true })) { const path = join(directory, entry.name); if (entry.isDirectory()) scan(path); else if (/\.(vue|ts)$/.test(path)) { const source = readFileSync(path, 'utf8'); for (const match of source.matchAll(/(?:\$t|\bt|\btr)\(\s*['"]([a-z0-9_]+)['"]\s*[,)]/g)) expect(ro, `${path}: ${match[1]}`).toHaveProperty(match[1]); } } }
    scan('resources/js');
});
it('uses the current XSRF cookie after a session rotates instead of a stale page meta token', async () => {
    const document = { cookie: 'XSRF-TOKEN=rotated%2Bfirst', querySelector: () => ({ content: 'stale-before-login' }) };
    vi.stubGlobal('document', document); const fetch = vi.fn().mockResolvedValue({ status: 200, ok: true, json: async () => ({ locale: 'en' }) }); vi.stubGlobal('fetch', fetch);
    await api('/locale','POST',{locale:'en'}); expect(fetch.mock.calls[0][1].headers['X-XSRF-TOKEN']).toBe('rotated+first'); expect(fetch.mock.calls[0][1].headers).not.toHaveProperty('X-CSRF-TOKEN');
    document.cookie = 'XSRF-TOKEN=rotated%2Bsecond'; await api('/locale','POST',{locale:'ro'}); expect(fetch.mock.calls[1][1].headers['X-XSRF-TOKEN']).toBe('rotated+second');
});
