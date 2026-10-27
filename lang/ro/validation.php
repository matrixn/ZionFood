<?php

return [
    'required' => 'Câmpul :attribute este obligatoriu.', 'required_if' => 'Câmpul :attribute este obligatoriu când :other este :value.',
    'present' => 'Câmpul :attribute trebuie să fie prezent.', 'string' => 'Câmpul :attribute trebuie să fie un text.',
    'email' => 'Câmpul :attribute trebuie să fie o adresă de e-mail validă.', 'unique' => 'Valoarea câmpului :attribute este deja utilizată.',
    'integer' => 'Câmpul :attribute trebuie să fie un număr întreg.', 'numeric' => 'Câmpul :attribute trebuie să fie un număr.',
    'boolean' => 'Câmpul :attribute trebuie să fie adevărat sau fals.', 'array' => 'Câmpul :attribute trebuie să fie o listă.',
    'in' => 'Valoarea selectată pentru :attribute nu este validă.', 'exists' => 'Valoarea selectată pentru :attribute nu există.',
    'confirmed' => 'Confirmarea câmpului :attribute nu corespunde.', 'regex' => 'Formatul câmpului :attribute nu este valid.',
    'url' => 'Câmpul :attribute trebuie să fie o adresă URL validă.', 'ulid' => 'Identificatorul din :attribute nu este valid.',
    'distinct' => 'Câmpul :attribute conține o valoare duplicată.', 'image' => 'Câmpul :attribute trebuie să fie o imagine.',
    'mimes' => 'Câmpul :attribute trebuie să fie un fișier de tipul: :values.', 'mimetypes' => 'Tipul fișierului :attribute nu este acceptat.',
    'dimensions' => 'Dimensiunile imaginii :attribute nu sunt acceptate.', 'uploaded' => 'Încărcarea fișierului :attribute a eșuat.',
    'date' => 'Câmpul :attribute trebuie să fie o dată validă.', 'date_format' => 'Câmpul :attribute trebuie să respecte formatul :format.',
    'after' => 'Câmpul :attribute trebuie să fie după :date.', 'before' => 'Câmpul :attribute trebuie să fie înainte de :date.',
    'min' => ['string' => 'Câmpul :attribute trebuie să aibă cel puțin :min caractere.', 'numeric' => 'Câmpul :attribute trebuie să fie cel puțin :min.', 'array' => 'Câmpul :attribute trebuie să conțină cel puțin :min elemente.', 'file' => 'Fișierul :attribute trebuie să aibă cel puțin :min KB.'],
    'max' => ['string' => 'Câmpul :attribute nu poate depăși :max caractere.', 'numeric' => 'Câmpul :attribute nu poate depăși :max.', 'array' => 'Câmpul :attribute nu poate conține mai mult de :max elemente.', 'file' => 'Fișierul :attribute nu poate depăși :max KB.'],
    'between' => ['string' => 'Câmpul :attribute trebuie să aibă între :min și :max caractere.', 'numeric' => 'Câmpul :attribute trebuie să fie între :min și :max.', 'array' => 'Câmpul :attribute trebuie să conțină între :min și :max elemente.', 'file' => 'Fișierul :attribute trebuie să aibă între :min și :max KB.'],
    'size' => ['string' => 'Câmpul :attribute trebuie să aibă :size caractere.', 'numeric' => 'Câmpul :attribute trebuie să fie :size.', 'array' => 'Câmpul :attribute trebuie să conțină :size elemente.', 'file' => 'Fișierul :attribute trebuie să aibă :size KB.'],
    'gte' => ['numeric' => 'Câmpul :attribute trebuie să fie cel puțin egal cu :value.', 'string' => 'Câmpul :attribute trebuie să aibă cel puțin :value caractere.', 'array' => 'Câmpul :attribute trebuie să aibă cel puțin :value elemente.', 'file' => 'Fișierul :attribute trebuie să aibă cel puțin :value KB.'],
    'attributes' => ['portion_grams' => 'greutatea unei porții', 'ingredients_text' => 'ingredientele pentru analiză', 'preparation' => 'detaliile de preparare', 'nutrition_inputs.portion_grams' => 'greutatea unei porții', 'nutrition_inputs.ingredients_text' => 'ingredientele pentru analiză', 'email' => 'e-mail', 'password' => 'parolă', 'name' => 'nume', 'role' => 'rol', 'location_ids' => 'locații atribuite', 'primary_color' => 'culoarea brandului', 'url' => 'adresa HTTPS', 'secret' => 'cheia de semnare', 'customer_name' => 'numele clientului', 'customer_phone' => 'telefonul clientului', 'fulfillment' => 'tipul comenzii', 'delivery_address' => 'adresa de livrare', 'postal_code' => 'codul poștal', 'delivery_zone_id' => 'zona de livrare', 'price' => 'preț', 'category_id' => 'categorie', 'items' => 'produse', 'logo' => 'logo', 'cover' => 'copertă', 'token' => 'codul de acces'],
];
