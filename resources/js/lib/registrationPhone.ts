import {
    getCountries,
    getCountryCallingCode,
    parsePhoneNumberFromString,
    type CountryCode,
} from 'libphonenumber-js/max';

const internationalNames = new Intl.DisplayNames(['en'], { type: 'region' });
export const phoneCountries = getCountries()
    .map((code) => ({
        code,
        name: internationalNames.of(code) ?? code,
        prefix: '+' + getCountryCallingCode(code),
    }))
    .sort((a, b) => a.name.localeCompare(b.name, 'en'));

export function registrationPhone(country: string, input: string): string | null {
    if (
        !phoneCountries.some((row) => row.code === country) ||
        input.length > 40 ||
        !/^\+?[0-9 ().-]+$/.test(input.trim())
    )
        return null;
    const number = parsePhoneNumberFromString(input.trim(), { defaultCountry: country as CountryCode, extract: false });
    return number?.country === country && number.isValid() && !number.ext ? number.number : null;
}
