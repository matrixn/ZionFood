import { expect, it } from 'vitest';
import { phoneCountries, registrationPhone } from '../../resources/js/lib/registrationPhone';

it.each([
    ['RO','0770 123 456','+40770123456'], ['RO','+40 770 123 456','+40770123456'],
    ['GB','020 7946 0018','+442079460018'], ['DE','030 901820','+4930901820'],
    ['US','(202) 555-0123','+12025550123'], ['CA','4165550123','+14165550123'],
    ['IT','02 3661 8300','+390236618300'], ['FR','06 12 34 56 78','+33612345678'],
])('validates and normalizes %s local numbers', (country, value, expected) => {
    expect(registrationPhone(country, value)).toBe(expected);
});
it.each([['RO','123'],['RO','+442079460018'],['US','+14165550123'],['CA','+12025550123'],['ZZ','0770123456'],['RO','0770123456 ext 12'],['RO','Call 0770123456']])('rejects invalid %s numbers and region mismatches', (country,value) => {
    expect(registrationPhone(country,value)).toBeNull();
});
it('lists international English country names and distinguishes shared calling prefixes', () => {
    expect(phoneCountries.find(row => row.code === 'RO')).toMatchObject({name:'Romania', prefix:'+40'});
    expect(phoneCountries.find(row => row.code === 'DE')).toMatchObject({name:'Germany', prefix:'+49'});
    expect(phoneCountries.find(row => row.code === 'US')?.prefix).toBe('+1');
    expect(phoneCountries.find(row => row.code === 'CA')?.prefix).toBe('+1');
    expect(phoneCountries.length).toBeGreaterThan(200);
});
