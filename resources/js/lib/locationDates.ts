import { usePage } from '@inertiajs/vue3';
import type { DisplaySettings } from '@/types';
export const dateFormats = ['d.m.Y', 'd/m/Y', 'Y-m-d', 'm/d/Y'];
export const dateTimeFormats = [
    'd.m.Y H:i',
    'd.m.Y H:i:s',
    'd/m/Y H:i',
    'd/m/Y H:i:s',
    'Y-m-d H:i',
    'Y-m-d H:i:s',
    'm/d/Y h:i A',
    'm/d/Y h:i:s A',
];
export interface DateContext {
    timezone?: string;
    display_settings?: Pick<DisplaySettings, 'date_format' | 'datetime_format'>;
}
export function locationDate(
    value: string | Date,
    context: DateContext = {},
    kind: 'date' | 'datetime' | 'time' = 'datetime',
): string {
    const pattern =
        kind === 'date'
            ? dateFormats.includes(context.display_settings?.date_format ?? '')
                ? context.display_settings!.date_format
                : dateFormats[0]
            : dateTimeFormats.includes(context.display_settings?.datetime_format ?? '')
              ? context.display_settings!.datetime_format
              : dateTimeFormats[0];
    let values: Record<string, string>;
    // Unzoned wire dates/slots already describe the venue calendar; never shift them through UTC.
    const local =
        typeof value === 'string' ? /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2})(?::(\d{2}))?)?$/.exec(value) : null;
    if (local)
        values = {
            year: local[1],
            month: local[2],
            day: local[3],
            hour: local[4] ?? '00',
            minute: local[5] ?? '00',
            second: local[6] ?? '00',
        };
    else {
        const date = new Date(value);
        if (!Number.isFinite(date.getTime())) return '—';
        values = Object.fromEntries(
            new Intl.DateTimeFormat('en-GB', {
                timeZone: context.timezone ?? 'Europe/Bucharest',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hourCycle: 'h23',
            })
                .formatToParts(date)
                .map((p) => [p.type, p.value]),
        );
    }
    const tokens: Record<string, string> = {
        Y: values.year,
        m: values.month,
        d: values.day,
        H: values.hour,
        h: String(Number(values.hour) % 12 || 12).padStart(2, '0'),
        i: values.minute,
        s: values.second,
        A: Number(values.hour) >= 12 ? 'PM' : 'AM',
    };
    const format = kind === 'time' ? pattern.slice(pattern.indexOf(' ') + 1) : pattern;
    return format.replace(/[YmdHhisA]/g, (token) => tokens[token]);
}
export function useLocationDates(override?: () => DateContext | undefined) {
    const page = usePage();
    const context = () => override?.() ?? (page.props.location as DateContext | undefined) ?? {};
    return {
        date: (v: string | Date) => locationDate(v, context(), 'date'),
        dateTime: (v: string | Date) => locationDate(v, context()),
        time: (v: string | Date) => locationDate(v, context(), 'time'),
    };
}
