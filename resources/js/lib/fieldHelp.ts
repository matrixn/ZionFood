import { i18n, tr } from '@/i18n';
// Semantic categories are shared by every form. Components can supply exact text for exceptional fields.
const patterns: [RegExp, string][] = [
    [/api.?key|secret|signing|token|cheie|semnare|password|parol/i, 'secret'],
    [/cui|tax.?id|fiscal|vat.?number/i, 'cui'],
    [/search|caut|filter|filtr/i, 'search'],
    [/email|e-mail/i, 'email'],
    [/phone|telefon/i, 'phone'],
    [/language|limb|locale/i, 'language'],
    [/timezone|fus.?orar/i, 'timezone'],
    [/latitude|longitude|latitudine|longitudine|coordinates/i, 'coordinates'],
    [/postal|zip|poștal/i, 'postal'],
    [/city|localitate|oraș/i, 'city'],
    [/country|țară/i, 'country'],
    [/address|adres|street|strad/i, 'address'],
    [
        /nutrition|nutrient|energie|energy|protein|carbohydrat|glucid|saturat|fat_g|grăsim|sugars|zahar|salt_g|fibre/i,
        'nutrition',
    ],
    [/portion|gram|weight|yield|serving|porți|cantitate.*g/i, 'weight'],
    [/allergen|alergen|may_contain/i, 'allergens'],
    [/frozen|congel/i, 'frozen'],
    [/ingredient/i, 'ingredients'],
    [/diet|label_|labels|etichete.*aliment/i, 'diet'],
    [/price|preț|valoare|amount|cost|fee|taxă|minimum.?order|comandă.*minim/i, 'money'],
    [/tax|tva|basis.?points/i, 'tax'],
    [/duration|minute|interval|cutoff|oprire|lead|înainte|avans/i, 'duration'],
    [/date|starts|ends|day|weekday|hours|opens|closes|program|ora|oră|ziua|data/i, 'schedule'],
    [/seats|capacity|locuri|capacitate|party.?size|guests|persoane|oaspeți/i, 'capacity'],
    [/role|rol|permission|access|acces/i, 'permissions'],
    [/location.?ids|assigned|atribuite|locații/i, 'locations'],
    [/daily.?limit|quota|cotă|limită|selection.?limit|min_choices|max_choices/i, 'limits'],
    [/model|provider|furnizor/i, 'provider'],
    [/webhook|https|endpoint|url|website|domeniu|domain|link/i, 'url'],
    [/logo|cover|photo|image|poză|imagine|copertă|file/i, 'image'],
    [/color|culoare|brand/i, 'branding'],
    [/quantity|cantitate|number|număr/i, 'quantity'],
    [/variant|size|dimensiun|opțiuni|options|modifier/i, 'options'],
    [/available|active|enabled|enable|activ|disponibil|accepting|servici/i, 'enabled'],
    [/status|stare/i, 'status'],
    [/description|descriere|notes|note|mesaj|message|preparation|preparare/i, 'description'],
    [/category|categorie|cuisine|specific|menu|meniu/i, 'classification'],
    [/terms|policy|politic|cancel|anulare|privacy|confidențial/i, 'policy'],
    [/name|nume|denumire|code|cod|area|sală/i, 'name'],
];
export function fieldHelp(field: string, label: string, kind = ''): string {
    const messages = i18n.global.messages.value;
    const current = messages[i18n.global.locale.value] as Record<string, string>;
    const english = messages.en as Record<string, string>;
    const key = Object.keys(current).find((key) => current[key] === label);
    const semantic = [field, key ?? '', key ? english[key] : '', label, kind].join(' ');
    const category =
        patterns.find(([pattern]) => pattern.test(semantic))?.[1] ??
        (['checkbox', 'ToggleSwitch', 'Checkbox'].includes(kind) ? 'enabled' : 'generic');
    return tr('field_help_' + category, { field: label || tr('field_value') });
}
