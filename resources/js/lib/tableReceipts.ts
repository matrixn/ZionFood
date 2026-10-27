const prefix = 'zf-table-receipts:';
export function readTableReceipts(table: string): string[] {
    try {
        const value: unknown = JSON.parse(sessionStorage.getItem(prefix + table) ?? '[]');
        return Array.isArray(value)
            ? [
                  ...new Set(value.filter((v): v is string => typeof v === 'string' && /^[A-Za-z0-9]{64}$/.test(v))),
              ].slice(-16)
            : [];
    } catch {
        return [];
    }
}
export function saveTableReceipts(table: string, tokens: string[]): void {
    try {
        sessionStorage.setItem(prefix + table, JSON.stringify([...new Set(tokens)].slice(-16)));
    } catch {
        /* Receipt display still works in memory when storage is unavailable. */
    }
}
