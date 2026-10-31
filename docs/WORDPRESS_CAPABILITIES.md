# Raport capabilități WordPress API — 0.0.44

Backendul ZionFood este pregătit pentru pluginul WordPress care urmează. Comparația folosește descrierile oficiale [GloriaFood din WordPress.org](https://wordpress.org/plugins/menu-ordering-reservations/) și [pagina pluginului](https://www.gloriafood.com/wordpress-restaurant-plugin). Importul integral din GloriaFood existent rămâne documentat separat în [Migration Center](MIGRATION_CENTER.md).

| Capabilitate | Livrat în API / aplicație | Limită explicită |
| --- | --- | --- |
| Conectare restaurant | Chei Sanctum, locații și permisiuni selectate, valabilitate limitată sau nedeterminată, rotire, revocare | Cheia se păstrează exclusiv în backendul viitorului plugin |
| Meniu digital | Branding, imagini, categorii, produse, gramaj/ingrediente, variante, alergeni/etichete/nutriție/traduceri salvate | AI și Migration Center se folosesc în panoul ZionFood; nu se pretinde o automatizare nouă în WordPress |
| Configurare meniu | Citire structură editabilă, produse CRUD și imagini, categorii, modificatori și opțiuni | Drepturi peste toate locațiile unui meniu comun; modificatorii păstrează protecția la nivel de organizație |
| Ridicare/livrare | Coș/quote și checkout cu prețuri server, zone poligonale/poștale, taxe și minime | UI-ul coșului în WordPress urmează; nu sunt implementate ore distincte pickup/delivery, programe de sărbători sau peste miezul nopții |
| Disponibilitate | Stare efectivă, program, pauză manuală, restricții de masă/serviciu | Checkout verifică din nou starea; pluginul trebuie să afișeze aceste informații |
| Comenzi programate — nou | Sloturi la 15 minute, avans/orizont/capacitate configurabile pe locație, ora pregătirii, coadă bucătărie și eveniment când devin scadente | Activare explicită; fără rezervare de stoc cantitativ și fără algoritm automat de capacitate a bucătăriei |
| Rezervări | Sloturi/capacitate, creare, dovadă privată, anulare și stări gestionate de personal | Funcțiile existente sunt reutilizate, fără plăți |
| Precomandă la rezervare — nou | Rezervare confirmată + token privat, comandă pentru ora rezervării, o singură precomandă neanulată/nerefuzată | Comanda și rezervarea au stări independente; personalul gestionează anularea ambelor când este necesar |
| Mese QR | Conectare client prin dovada QR, bon activ, listă/controale personal, detalii comenzi și marcare servit | Programul și oprirea înainte de închidere rămân obligatorii |
| Promoții — nou | Cupoane și promoții automate: procent, sumă fixă, livrare gratuită, buy X get Y; limite, minime, perioadă, tipuri de comandă și produse eligibile | Patru tipuri implementate, fără promisiunea parității tuturor șabloanelor GloriaFood; fără cumul, segmentare clienți sau marketing |
| Operațiuni | Liste comenzi, KDS, istoric și motiv refuz, concurență prin versiune, snapshots | Nu se acceptă prețuri sau stări arbitrare de la client |
| Multi-locație | Manifest și acces numai la locațiile selectate ale restaurantului | Nu conferă drepturi asupra altui restaurant din aceeași organizație |
| Rapoarte | Statistici autorizate pe module, top produse/mese, rezervări și vizite aproximative existente | Valorile comenzilor nu sunt încasări; sesiunile QR nu sunt persoane/ocupare fizică |
| Comunicații — nou | Jurnale restaurant opționale în meniu, platformă pentru toate restaurantele, filtre, corelare, status/durată/încercări | Doar metadate tehnice, retenție implicită 30 zile |
| Sincronizare — nou | Webhooks HMAC cu retry, outbox, feed de recuperare cu cursor și idempotency | Livrare at-least-once; pluginul trebuie să verifice semnătura, să deduplice și să reconcilieze |
| Butoane/shortcode/blocuri WordPress | Datele și URL-urile necesare sunt disponibile | **Pluginul nu este construit în acest commit** |
| Plăți/tips/rezervări plătite | Declarate indisponibile în manifest | Placeholder, conform deciziei utilizatorului |
| Marketing automat, aplicații mobile, imprimare | Nicio integrare prezentată ca funcțională | Etape separate, conform deciziei utilizatorului |

Securitatea folosește pachetele deja instalate, fără dependențe adăugate artificial. Transportul sigur și recuperarea reduc riscul erorilor de rețea; nu pot garanta o comunicație „infailibilă”. [Contractul API](WORDPRESS_API.md) descrie câmpurile, permisiunile, reluările, semnăturile și limitele operaționale. Verificarea locală și limitele testării se înregistrează în [CODEX_PROGRESS.md](CODEX_PROGRESS.md).
