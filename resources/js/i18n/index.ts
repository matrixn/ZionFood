import { createI18n } from 'vue-i18n';
import en from './en.json';
import ro from './ro.json';
export type Locale = 'ro' | 'en';
export const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: 'ro' as Locale,
    fallbackLocale: 'en',
    messages: { ro, en },
    missingWarn: false,
    fallbackWarn: false,
});
export const tr = (key: string, values?: Record<string, string | number>): string =>
    values ? i18n.global.t(key, values) : i18n.global.t(key);
export const localeTag = (): string => (i18n.global.locale.value === 'ro' ? 'ro-RO' : 'en-GB');
export function primeLocale(locale: Locale) {
    const romanian = locale === 'ro';
    return {
        startsWith: romanian ? 'Începe cu' : 'Starts with',
        contains: romanian ? 'Conține' : 'Contains',
        notContains: romanian ? 'Nu conține' : 'Not contains',
        endsWith: romanian ? 'Se termină cu' : 'Ends with',
        equals: romanian ? 'Egal cu' : 'Equals',
        notEquals: romanian ? 'Diferit de' : 'Not equals',
        noFilter: romanian ? 'Fără filtru' : 'No filter',
        clear: romanian ? 'Șterge' : 'Clear',
        apply: romanian ? 'Aplică' : 'Apply',
        accept: romanian ? 'Confirmă' : 'Confirm',
        reject: romanian ? 'Renunță' : 'Cancel',
        choose: romanian ? 'Alege' : 'Choose',
        upload: romanian ? 'Încarcă' : 'Upload',
        cancel: romanian ? 'Renunță' : 'Cancel',
        emptyMessage: romanian ? 'Niciun rezultat' : 'No results found',
        emptyFilterMessage: romanian ? 'Niciun rezultat' : 'No results found',
        searchMessage: romanian ? '{0} rezultate disponibile' : '{0} results available',
        selectionMessage: romanian ? '{0} elemente selectate' : '{0} items selected',
        emptySelectionMessage: romanian ? 'Niciun element selectat' : 'No selected item',
        firstDayOfWeek: 1,
        dayNames: romanian
            ? ['Duminică', 'Luni', 'Marți', 'Miercuri', 'Joi', 'Vineri', 'Sâmbătă']
            : ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        dayNamesShort: romanian
            ? ['Du', 'Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ']
            : ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        dayNamesMin: romanian ? ['Du', 'Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ'] : ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
        monthNames: romanian
            ? [
                  'Ianuarie',
                  'Februarie',
                  'Martie',
                  'Aprilie',
                  'Mai',
                  'Iunie',
                  'Iulie',
                  'August',
                  'Septembrie',
                  'Octombrie',
                  'Noiembrie',
                  'Decembrie',
              ]
            : [
                  'January',
                  'February',
                  'March',
                  'April',
                  'May',
                  'June',
                  'July',
                  'August',
                  'September',
                  'October',
                  'November',
                  'December',
              ],
        monthNamesShort: romanian
            ? ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Noi', 'Dec']
            : ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        today: romanian ? 'Astăzi' : 'Today',
        dateFormat: 'dd.mm.yy',
        aria: {
            close: romanian ? 'Închide' : 'Close',
            previous: romanian ? 'Anterior' : 'Previous',
            next: romanian ? 'Următor' : 'Next',
            firstPageLabel: romanian ? 'Prima pagină' : 'First page',
            lastPageLabel: romanian ? 'Ultima pagină' : 'Last page',
            previousPageLabel: romanian ? 'Pagina anterioară' : 'Previous page',
            nextPageLabel: romanian ? 'Pagina următoare' : 'Next page',
            pageLabel: romanian ? 'Pagina {page}' : 'Page {page}',
            selectAll: romanian ? 'Selectează tot' : 'Select all',
            unselectAll: romanian ? 'Deselectează tot' : 'Unselect all',
            selectRow: romanian ? 'Selectează rândul' : 'Select row',
            unselectRow: romanian ? 'Deselectează rândul' : 'Unselect row',
            trueLabel: romanian ? 'Da' : 'True',
            falseLabel: romanian ? 'Nu' : 'False',
            nullLabel: romanian ? 'Neselectat' : 'Not selected',
            navigation: romanian ? 'Navigare' : 'Navigation',
            expandRow: romanian ? 'Extinde rândul' : 'Expand row',
            collapseRow: romanian ? 'Restrânge rândul' : 'Collapse row',
        },
    };
}
