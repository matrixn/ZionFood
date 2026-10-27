// Stable vocabulary is local; translating a menu never changes nutrient numbers.
const allergenCodes = [
    'gluten',
    'crustaceans',
    'eggs',
    'fish',
    'peanuts',
    'soy',
    'milk',
    'nuts',
    'celery',
    'mustard',
    'sesame',
    'sulphites',
    'lupin',
    'molluscs',
];
const nutrientCodes = [
    'energy_kj',
    'energy_kcal',
    'fat_g',
    'saturates_g',
    'carbohydrate_g',
    'sugars_g',
    'protein_g',
    'salt_g',
    'fibre_g',
];
const labelCodes = ['vegetarian', 'vegan', 'gluten_free', 'halal', 'nut_free', 'dairy_free', 'hot', 'raw'];
const rows: Record<string, string[]> = {
    de: [
        'Glutenhaltiges Getreide|Krebstiere|Eier|Fisch|Erdnüsse|Soja|Milch einschließlich Laktose|Schalenfrüchte|Sellerie|Senf|Sesam|Schwefeldioxid und Sulfite|Lupinen|Weichtiere',
        'Energie (kJ)|Energie (kcal)|Fett (g)|Gesättigte Fettsäuren (g)|Kohlenhydrate (g)|Zucker (g)|Eiweiß (g)|Salz (g)|Ballaststoffe (g)',
        'Vegetarisch|Vegan|Glutenfrei|Halal|Ohne Nüsse und Erdnüsse|Ohne Milchprodukte|Scharf|Roh',
        'Enthält Allergene|Kann Spuren enthalten|Nährwerte|Pro 100 g|Pro 100 ml|Pro Portion',
    ],
    fr: [
        'Céréales contenant du gluten|Crustacés|Œufs|Poisson|Arachides|Soja|Lait, y compris lactose|Fruits à coque|Céleri|Moutarde|Sésame|Dioxyde de soufre et sulfites|Lupin|Mollusques',
        'Énergie (kJ)|Énergie (kcal)|Matières grasses (g)|Acides gras saturés (g)|Glucides (g)|Sucres (g)|Protéines (g)|Sel (g)|Fibres (g)',
        'Végétarien|Végétalien|Sans gluten|Halal|Sans fruits à coque ni arachides|Sans produits laitiers|Épicé|Cru',
        'Contient des allergènes|Peut contenir des traces de|Valeurs nutritionnelles|Pour 100 g|Pour 100 ml|Par portion',
    ],
    it: [
        'Cereali contenenti glutine|Crostacei|Uova|Pesce|Arachidi|Soia|Latte, incluso lattosio|Frutta a guscio|Sedano|Senape|Sesamo|Anidride solforosa e solfiti|Lupini|Molluschi',
        'Energia (kJ)|Energia (kcal)|Grassi (g)|Grassi saturi (g)|Carboidrati (g)|Zuccheri (g)|Proteine (g)|Sale (g)|Fibre (g)',
        'Vegetariano|Vegano|Senza glutine|Halal|Senza frutta a guscio e arachidi|Senza latticini|Piccante|Crudo',
        'Contiene allergeni|Può contenere tracce di|Valori nutrizionali|Per 100 g|Per 100 ml|Per porzione',
    ],
    es: [
        'Cereales con gluten|Crustáceos|Huevos|Pescado|Cacahuetes|Soja|Leche, incluida lactosa|Frutos de cáscara|Apio|Mostaza|Sésamo|Dióxido de azufre y sulfitos|Altramuces|Moluscos',
        'Energía (kJ)|Energía (kcal)|Grasas (g)|Grasas saturadas (g)|Hidratos de carbono (g)|Azúcares (g)|Proteínas (g)|Sal (g)|Fibra (g)',
        'Vegetariano|Vegano|Sin gluten|Halal|Sin frutos de cáscara ni cacahuetes|Sin lácteos|Picante|Crudo',
        'Contiene alérgenos|Puede contener trazas de|Valores nutricionales|Por 100 g|Por 100 ml|Por porción',
    ],
    hu: [
        'Glutént tartalmazó gabonafélék|Rákfélék|Tojás|Hal|Földimogyoró|Szója|Tej, beleértve a laktózt|Diófélék|Zeller|Mustár|Szezámmag|Kén-dioxid és szulfitok|Csillagfürt|Puhatestűek',
        'Energia (kJ)|Energia (kcal)|Zsír (g)|Telített zsírsavak (g)|Szénhidrát (g)|Cukrok (g)|Fehérje (g)|Só (g)|Rost (g)',
        'Vegetáriánus|Vegán|Gluténmentes|Halal|Dióféléktől és földimogyorótól mentes|Tejtermékmentes|Csípős|Nyers',
        'Allergéneket tartalmaz|Nyomokban tartalmazhat|Tápérték|100 g-ban|100 ml-ben|Adagonként',
    ],
    uk: [
        'Злаки, що містять глютен|Ракоподібні|Яйця|Риба|Арахіс|Соя|Молоко, включно з лактозою|Горіхи|Селера|Гірчиця|Кунжут|Діоксид сірки та сульфіти|Люпин|Молюски',
        'Енергія (кДж)|Енергія (ккал)|Жири (г)|Насичені жири (г)|Вуглеводи (г)|Цукри (г)|Білки (г)|Сіль (г)|Клітковина (г)',
        'Вегетаріанське|Веганське|Без глютену|Халяль|Без горіхів та арахісу|Без молочних продуктів|Гостре|Сире',
        'Містить алергени|Може містити сліди|Харчова цінність|На 100 г|На 100 мл|На порцію',
    ],
    bg: [
        'Зърнени култури с глутен|Ракообразни|Яйца|Риба|Фъстъци|Соя|Мляко, включително лактоза|Ядки|Целина|Синап|Сусам|Серен диоксид и сулфити|Лупина|Мекотели',
        'Енергия (kJ)|Енергия (kcal)|Мазнини (g)|Наситени мазнини (g)|Въглехидрати (g)|Захари (g)|Белтъчини (g)|Сол (g)|Фибри (g)',
        'Вегетарианско|Веганско|Без глутен|Халал|Без ядки и фъстъци|Без млечни продукти|Люто|Сурово',
        'Съдържа алергени|Може да съдържа следи от|Хранителна стойност|За 100 g|За 100 ml|За порция',
    ],
    tr: [
        'Glüten içeren tahıllar|Kabuklu deniz ürünleri|Yumurta|Balık|Yer fıstığı|Soya|Laktoz dahil süt|Sert kabuklu yemişler|Kereviz|Hardal|Susam|Kükürt dioksit ve sülfitler|Acı bakla|Yumuşakçalar',
        'Enerji (kJ)|Enerji (kcal)|Yağ (g)|Doymuş yağ (g)|Karbonhidrat (g)|Şekerler (g)|Protein (g)|Tuz (g)|Lif (g)',
        'Vejetaryen|Vegan|Glütensiz|Helal|Sert kabuklu yemiş ve yer fıstığı içermez|Süt ürünü içermez|Acı|Çiğ',
        'Alerjen içerir|Eser miktarda içerebilir|Besin değerleri|100 g başına|100 ml başına|Porsiyon başına',
    ],
    pl: [
        'Zboża zawierające gluten|Skorupiaki|Jaja|Ryby|Orzeszki ziemne|Soja|Mleko, w tym laktoza|Orzechy|Seler|Gorczyca|Sezam|Dwutlenek siarki i siarczyny|Łubin|Mięczaki',
        'Energia (kJ)|Energia (kcal)|Tłuszcz (g)|Kwasy tłuszczowe nasycone (g)|Węglowodany (g)|Cukry (g)|Białko (g)|Sól (g)|Błonnik (g)',
        'Wegetariańskie|Wegańskie|Bez glutenu|Halal|Bez orzechów i orzeszków ziemnych|Bez nabiału|Pikantne|Surowe',
        'Zawiera alergeny|Może zawierać śladowe ilości|Wartości odżywcze|Na 100 g|Na 100 ml|Na porcję',
    ],
};
const misc = ['allergens', 'may_contain', 'nutrition', 'per_100g', 'per_100ml', 'per_serving'];
export const foodVocabularies = Object.fromEntries(
    Object.entries(rows).map(([language, values]) => [
        language,
        Object.fromEntries([
            ...values[0].split('|').map((name, index) => ['allergen_group_' + (index + 1), name]),
            ...values[1].split('|').map((name, index) => ['mc_nutrient_' + nutrientCodes[index], name]),
            ...values[2].split('|').map((name, index) => ['mc_label_' + labelCodes[index], name]),
            ...values[3].split('|').map((name, index) => ['mc_' + misc[index], name]),
        ]),
    ]),
) as Record<string, Record<string, string>>;
export function foodLabel(language: string, key: string, fallback: (key: string) => string): string {
    return foodVocabularies[language]?.[key] ?? fallback(key);
}
export { allergenCodes };
