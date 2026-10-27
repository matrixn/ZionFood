import {it,expect,afterEach} from 'vitest';
import {i18n} from '../../resources/js/i18n';
import {fieldHelp} from '../../resources/js/lib/fieldHelp';
afterEach(()=>{i18n.global.locale.value='ro';});
it('explains units, credentials and safety fields without including input values',()=>{
 expect(fieldHelp('portion_grams','Greutatea unei porții')).toContain('grame');
 expect(fieldHelp('api_key','Cheie API')).toContain('confidențiale');
 expect(fieldHelp('allergens','Conține alergeni')).toContain('nu garantează');
 expect(fieldHelp('ingredients_allergens','Conține alergeni')).toContain('nu garantează');
});
it('localizes contextual help while retaining stable model and enum identifiers',()=>{
 i18n.global.locale.value='en';expect(fieldHelp('portion_grams','Weight of one portion')).toContain('grams');
 expect(fieldHelp('allergens','Contains allergens')).toContain('recipe');
 expect(fieldHelp('unknown','Custom field')).toContain('Custom field');
});
