import { it, expect, vi } from 'vitest';
import { readTableReceipts, saveTableReceipts } from '../../resources/js/lib/tableReceipts';
it('keeps validated receipt tokens scoped to the table and tolerates blocked storage', () => {
    const map = new Map<string, string>();
    vi.stubGlobal('sessionStorage', {
        getItem: (k: string) => map.get(k) ?? null,
        setItem: (k: string, v: string) => map.set(k, v),
    });
    const a = 'a'.repeat(64),
        b = 'b'.repeat(64);
    saveTableReceipts('A', [a, a, b]);
    expect(readTableReceipts('A')).toEqual([a, b]);
    expect(readTableReceipts('B')).toEqual([]);
    map.set('zf-table-receipts:A', '["invalid",123]');
    expect(readTableReceipts('A')).toEqual([]);
    map.set('zf-table-receipts:A', 'broken');
    expect(readTableReceipts('A')).toEqual([]);
    vi.stubGlobal('sessionStorage', {
        getItem: () => {
            throw Error('blocked');
        },
        setItem: () => {
            throw Error('blocked');
        },
    });
    expect(readTableReceipts('A')).toEqual([]);
    expect(() => saveTableReceipts('A', [a])).not.toThrow();
    vi.unstubAllGlobals();
});
