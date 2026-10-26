# ZionFood

![ZionFood — comenzi, rezervări și administrarea restaurantului](docs/assets/zionfood-hero-ai.png)

**Versiune: 0.0.9** · Platformă SaaS pentru restaurante · Română implicit, engleză opțional.

ZionFood reunește configurarea restaurantului, meniul public, comenzile, rezervările, comenzile la masă prin QR și operațiunile din bucătărie. Datele sunt organizate pe **Organizație → Restaurant → Locație**, cu acces separat pentru administrarea platformei și echipa restaurantului.

Imaginea este o ilustrație generată cu AI, nu o captură din aplicație. [Prompt și proveniență](docs/assets/zionfood-hero-ai.prompt.md).

## Cum se citește istoricul

- `[x]` = funcționalitate implementată sau etapă finalizată în cod.
- `[ ]` = funcționalitate rămasă de implementat sau verificare operațională încă necesară.

Bifele nu certifică automat un deployment în producție. Rezultatele și limitele verificărilor sunt consemnate în [CODEX_PROGRESS.md](docs/CODEX_PROGRESS.md).

## Istoricul proiectului

### 25 octombrie 2026 — baza MVP

- [x] Inițializarea repository-ului și README-ului — `4a93f6a`.
- [x] MVP `0.0.1`: autentificare, onboarding, structură multi-tenant, meniuri, checkout, urmărirea comenzilor și bucătărie — `91ba816`.
- [x] Arhitectură, securitate, API-uri, documentația implementării și benchmark-uri locale — `ee24976`.

### 26 octombrie 2026 — administrare și email

- [x] Compatibilitate PDO MySQL pentru PHP 8.4 — `e0c8154`.
- [x] Administrarea personalului, branding, RO/EN și webhooks semnate — `e6cdc5e`.
- [x] Integrarea transportului de email Resend — `57c0215`.
- [x] Coadă comună de email, limita de 140 de destinatari/24h, alerte de erori și pagina de test — `626db6c`.
- [x] Acces public temporar la `/test-email`, cu CSRF și limitarea cererilor — `f1df6c9`.
- [x] Corecția workerilor Horizon pentru medii Forge personalizate și loguri de trimitere — `2c9e853`.

### 26 octombrie 2026 — setup și operarea restaurantului

- [x] Setup ghidat, datele locației, săli/mese/capacitate și verificări înainte de publicare — `dec645e`.
- [x] Preferință opțională per locație pentru viitorul flux de catering/evenimente — `23136c0`.
- [x] Rezervări efective, QR permanent, închiderea meniului mesei de către personal, limită înainte de închiderea localului și zone de livrare desenate — `4e79565`.
- [x] Catalog culinar cu 27 de opțiuni ilustrate, administrare generală și selecție multiplă separată în setup — `cf555b0`.

### Versiuni și documentație

- [x] `0.0.2`: versiuni sincronizate și regulă de incrementare patch la fiecare commit nou — `45477fa`.
- [x] `0.0.3`: README cu istoric, stadiul implementării, lucrări rămase și imagine ZionFood generată cu AI.

Detaliile etapelor se găsesc în [CHANGELOG.md](CHANGELOG.md) și [istoricul verificărilor](docs/CODEX_PROGRESS.md). Commiturile deja publicate își păstrează istoricul original.

- [x] `0.0.4`: pagină publică de prezentare RO/EN, imagini generate, animații, previzualizări interactive și CTA-uri către cont/panou.

- [x] `0.0.5`: localizarea restaurantului după adresa din formular, mesaje pentru date lipsă și pin ajustabil, fără câmpuri latitudine/longitudine — `81ed958`.
- [x] `0.0.6`: documentarea detaliată, cu bife, a localizării restaurantului și a comportamentului hărții.
- [x] `0.0.7`: preluarea adresei din pin și centrarea cu zoom a zonelor de livrare pe restaurant — `95a0843`.
- [x] `0.0.8`: ghid de utilizare pentru adresa din pin și harta zonelor de livrare, cu istoricul actualizat.

- [x] `0.0.9`: navigare grupată, pregătirea restaurantului prima sub selectorul locației și editori integrați în wizard pentru program, livrare, meniu, identitate, personal și locații.

## Implementat

### Prima pagină de prezentare

- [x] Landing page RO/EN, fotografie de prezentare generată cu AI, navigare mobilă și CTA-uri funcționale.
- [x] Previzualizări pentru meniu/rezervări/QR/livrare, demonstrație interactivă de comandă, FAQ și animații care respectă preferința de mișcare redusă.
- [x] Ilustrație de bucătărie, metadate și texte alternative localizate; meniurile restaurantelor rămân pe hosturile lor.

### Conturi, organizații și acces

- [x] Înregistrare, autentificare, deconectare, verificarea emailului și resetarea parolei prin Laravel Fortify.
- [x] Onboarding cu organizație, proprietar, restaurant, locație, meniu, program, subdomeniu și trial de 14 zile.
- [x] Autorizare pe organizație/locație, politici și chei externe compuse pentru separarea datelor.
- [x] Roluri owner/manager/staff, atribuirea locațiilor și păstrarea accesului proprietarului.
- [x] Invitații cu expirare, acceptare și revocare; eliminarea accesului și revocarea tokenurilor asociate.
- [x] Panou general pentru organizații/trial și acces protejat la Horizon; administratorul platformei este separat de rolurile restaurantului.

### Setup, branding și specific culinar

- [x] „Pregătește restaurantul” imediat sub selectorul locației; meniul este grupat în Operațiuni, Administrarea restaurantului și Conectări.
- [x] Configurarea programului, zonelor de livrare, meniului, identității și personalului în wizard, cu salvare pe același pas și avertizare la părăsirea modificărilor nesalvate.
- [x] Lista locațiilor accesibile, cu acces la configurare pentru owner/manager și păstrarea wizard-ului la schimbarea locației.

- [x] Setup cu submeniuri, progres calculat pe server, protecție pentru salvări concurente și avertizare pentru modificări nesalvate.
- [x] Nume, adresă, localitate, contacte, website și fus orar; buton de localizare după adresă, feedback pentru date lipsă și hartă cu pin ajustabil.
- [x] „Localizează restaurantul” folosește adresa curentă din formular, inclusiv modificările încă nesalvate.
- [x] Mesaje RO/EN care enumeră datele lipsă și explică adresele negăsite sau pozițiile aproximative.
- [x] Alegere explicită între mai multe adrese găsite; fără selectarea automată a primului rezultat.
- [x] Poziționare și ajustare a pinului prin apăsare pe hartă sau mutare, cu păstrare prin „Salvează pasul”.
- [x] Ștergerea pinului vechi când se modifică adresa și ignorarea răspunsurilor de căutare depășite.
- [x] „Preia adresa de la pin” completează strada/numărul, localitatea, țara și codul poștal disponibile, păstrând poziția aleasă; datele lipsă sunt semnalate.
- [x] Editorul zonelor de livrare pornește cu zoom pe pinul salvat, marchează restaurantul și oferă „Centrează pe restaurant”; când există doar adresa, o localizează o singură dată, cu alegere explicită pentru rezultate multiple.
- [x] Căutare Photon protejată pentru owner/manager, cache și limitarea cererilor; furnizor configurabil, fără câmpuri numerice de coordonate.
- [x] Capacitate totală sau plan de mese cu coduri unice, săli/terase și număr de locuri.
- [x] Activare independentă per locație pentru ridicare, livrare, rezervări și comenzi QR.
- [x] Date private pentru companie/politici și verificări pentru publicarea comenzilor.
- [x] Branding: nume/slug, tagline, descriere, logo, copertă, culori, contacte și previzualizare.
- [x] **Specific culinar** imediat după **Nume și adresă**, cu căutare, fotografii/descrieri, selecție multiplă și listă separată.
- [x] Feedback pentru selecții nesalvate și „Activ din…” după salvare; păstrarea datei inițiale la salvări fără schimbarea selecției.
- [x] Catalog general cu adăugare/editare, fotografii, RO/EN, ordine și disponibilitate; toate cele 27 de categorii solicitate și [atribuiri](docs/CUISINE_PHOTO_CREDITS.md).
- [x] Interfețe RO/EN cu selector persistent cu steag și numele limbii, validări și notificări localizate.

### Meniu, comenzi și bucătărie

- [x] Categorii, produse, fotografii, disponibilitate, variante și grupuri de opțiuni/modificatori.
- [x] Meniu public, personalizarea produselor, coș și checkout pentru ridicare, livrare sau masă.
- [x] Prețuri, taxe de livrare și totaluri calculate pe server în bani; snapshot-uri independente de modificările ulterioare ale meniului.
- [x] Cote de taxă pe produse păstrate în snapshot-urile comenzilor.
- [x] Checkout tranzacțional și idempotent: retrimiterea aceleiași cereri nu creează o comandă suplimentară.
- [x] Urmărirea privată a comenzii prin token, istoric al stărilor, filtre, paginare și detalii pentru echipă.
- [x] KDS cu acceptare/respingere, pregătire, gata, expediere, finalizare și anulare, în funcție de tipul comenzii.
- [x] Reverb/Echo privat, protecție pentru versiuni concurente, polling și recuperare după reconectare.
- [x] Sunet după activarea de către utilizator și mod fullscreen în bucătărie.
- [x] Outbox tranzacțional, dispatch după commit, reîncercări și reconciliere prin scheduler.

### Rezervări, QR și livrare

- [x] Rezervări publice cu verificarea programului, duratei, anticipării, numărului de persoane și capacității.
- [x] Alocarea unei mese potrivite sau a capacității comune; protecție tranzacțională împotriva suprarezervării.
- [x] Confirmare automată/manuală, locuri ținute temporar și expirarea cererilor neconfirmate.
- [x] Confirmare, respingere, așezare la masă, finalizare, anulare și no-show pentru personal.
- [x] Confirmare privată, anulare de către client în termenul permis și email opțional către client.
- [x] QR permanent per masă, descărcare, previzualizare și rotația credentialelor.
- [x] Închiderea/redeschiderea meniului mesei de către personal; scanarea din nou nu anulează închiderea.
- [x] Limită configurabilă de 0–240 minute înainte de închiderea localului, implicit 30, verificată și pentru sesiuni QR deja deschise.
- [x] Masa și sala păstrate în comandă și afișate în bucătărie.
- [x] Editor **Leaflet + Geoman** în **Setări > Stabilește ritmul**, cu poligoane, editare și fundal configurabil.
- [x] Pin de livrare ales de client; serverul verifică zona și aplică prioritatea, taxa și minimul comenzii.
- [x] Vechile zone după prefix poștal funcționează atunci când nu există poligoane active.

### Email, integrare și diagnostic

- [x] Resend prin driverul Laravel, comandă de test și configurare documentată pentru Forge.
- [x] Emailuri externe prin coadă criptată, cu limită comună de **140 de destinatari într-o fereastră de 24h** și amânarea surplusului.
- [x] Alerte de erori/excepții raportate de Laravel către `ADMIN_EMAIL`, cu deduplicare și protecție împotriva buclelor de alertare.
- [x] `/test-email` temporar public, cu destinatar fix din configurație, CSRF și maximum trei cereri/oră.
- [x] `zionfood:mail-status` și loguri corelate pentru coadă, procesare, acceptarea de către furnizor, reîncercări și erori finale.
- [x] Plan Horizon de rezervă pentru medii personalizate, pe lângă mediile production/local.
- [x] API v1 și OpenAPI; tokenuri Sanctum pe organizație/abilități, cu expirare și revocare.
- [x] Webhooks semnate pentru comenzi/meniu/produse, cu istoric, retry, lease și reconciliere.
- [x] Protecții webhook: verificarea DNS/IP, adresă fixată în cURL, TLS, fără redirect/proxy și răspunsuri limitate.

### Mediu de dezvoltare și documentație

- [x] Servicii locale MariaDB/Redis prin Docker Compose și script de configurare a mediului.
- [x] Demo-uri pentru trei restaurante, date de test și credențiale generate în storage privat.
- [x] Teste Pest/PHP, Vitest, Playwright, concurență și scenarii k6 cu condițiile măsurătorilor documentate.
- [x] Workflow GitHub Actions pentru verificări PHP/frontend, stil, build și teste SQLite/MariaDB; configurația există, iar rezultatul fiecărei rulări se verifică separat.
- [x] Arhitectură, securitate, schemă DB, OpenAPI, deployment, changelog și jurnal de progres în repository.

## Ce mai este de implementat sau finalizat

### Funcționalități

- [ ] **Plăți efective** și onboarding la un furnizor. Meniul Plăți este momentan placeholder.
- [ ] Politici pentru rezervări plătite: avans fix/rezervare, avans/persoană sau plata integrală a unui meniu/pachet, plus anulare/rambursare/no-show.
- [ ] Flux complet de **catering/evenimente off-site**: dată, adresă, participanți, ofertare și execuție. Preferința opțională există deja în setup.
- [ ] **Comenzi programate** și regulile lor de capacitate/fulfillment; există doar pregătirea preferinței.
- [ ] Programe cu intervale multiple, peste miezul nopții, pe serviciu și pentru sărbători/excepții.
- [ ] Combinarea meselor, legarea automată a rezervării de sesiunea QR și gestionarea comună a ocupării cu walk-in.
- [ ] Alerte de rezervare pentru personal, SMS și webhooks dedicate rezervărilor.
- [ ] Ecrane complete pentru crearea mai multor restaurante/locații; structura și selectorul de locație există deja.
- [ ] Plugin WordPress separat și provisionarea automatizată a domeniilor personalizate/DNS/TLS.
- [ ] Ciclu complet de billing și abonamente după trial.
- [ ] Geocodare/căutare de adrese pentru client la checkout și extinderi pentru poligoane cu goluri sau MultiPolygon. Momentan pinul este autoritar, iar adresa textuală nu este verificată automat.
- [ ] Persistența coșului la reîncărcare completă și urmărirea portabilă a comenzii între dispozitive.
- [ ] Traducerea conținutului introdus de restaurant; RO/EN se aplică interfeței, nu traduce automat produsele.

### Operare și verificări de producție

- [ ] Verificarea completă a fluxurilor curente pe Forge, cu DNS/TLS/subdomenii și configurația fiecărei locații.
- [ ] Emailuri reale verificate pentru toate fluxurile relevante și recepția în inbox; acceptarea de către Resend nu dovedește singură recepția în inbox.
- [ ] Alegerea și verificarea furnizorului de hărți pentru traficul de producție, conform limitelor sale de utilizare.
- [ ] QR-uri tipărite pe dispozitive reale, accesibilitate, dark mode și comportamentul touch/sunet/fullscreen.
- [ ] Politici de retenție/ștergere pentru date personale, idempotency și outbox; arhivare și proceduri de backup/restaurare/incidente.
- [ ] Măsurători de capacitate și recuperare după întreruperi pe infrastructura țintă; benchmark-urile locale nu sunt garanții de producție.
- [ ] Eliminarea rutei publice temporare `/test-email` după încheierea diagnosticării.

## Verificări executate

Navigarea și editorii integrați (`0.0.9`) au trecut **152 de teste backend / 948 de aserțiuni pe SQLite**, 8 teste frontend, TypeScript/build, ESLint și Pint pe Linux. Trei scenarii de browser au verificat setup-ul existent, salvările în wizard, avertizarea pentru modificări nesalvate, gruparea meniului, locațiile și toate cele șase ecrane integrate pe mobil, în RO/EN. Capturile desktop/mobil au fost inspectate.

Preluarea adresei din pin și centrarea zonelor de livrare (`0.0.7`) au trecut **148 de teste backend / 784 de aserțiuni pe SQLite**, 8 teste frontend, TypeScript/build, ESLint și Pint pe Linux. Șase scenarii de browser au verificat localizarea, completarea și salvarea adresei/pinului, adresele incomplete și răspunsurile întârziate, zoom-ul și selecția adresei, RO/EN/mobil, setup-ul și desenarea/salvarea unei zone cu checkout pe hartă. A fost verificată și preluarea reală a adresei unui pin public din București.

Localizarea restaurantului (`0.0.5`) a trecut **144 de teste backend / 738 de aserțiuni pe SQLite**, 8 teste frontend, TypeScript/build, ESLint și Pint pe Linux. Trei scenarii Playwright au verificat datele lipsă, harta și salvarea pinului, alegerea adreselor, erorile, RO/EN, mobil și regresia setup-ului. A trecut și regresia de browser pentru desenarea unei zone de livrare și checkout cu pin pe hartă. A fost verificată și o căutare reală pentru o adresă publică din București. Detalii în [RESTAURANT_LOCATION.md](docs/RESTAURANT_LOCATION.md).

Pagina de prezentare a fost verificată prin două scenarii Playwright pentru RO/EN, CTA-uri, taburi și tastatură, demo fără operațiuni reale, imagini, mobil și mișcare redusă. Regresia backend SQLite a trecut 138 de teste / 712 aserțiuni; TypeScript/build, ESLint, 8 teste frontend și Pint au trecut. Detalii în [LANDING_PAGE.md](docs/LANDING_PAGE.md).

Etapa catalogului culinar a trecut **138 de teste backend / 698 de aserțiuni pe SQLite și MariaDB**, 8 teste frontend, TypeScript, ESLint, build și Pint. Playwright a verificat selecția/salvarea catalogului, imaginile, RO/EN, layout-ul mobil, administrarea cu upload și regresia setup-ului.

Etapele anterioare includ concurență pentru checkout și rezervări, fluxuri QR/livrare în browser și [benchmark-uri locale cu condiții documentate](docs/BENCHMARKS.md). Incidentul Horizon și confirmările Resend observate atunci sunt descrise în [documentația de operare](docs/DEPLOYMENT.md#mail-delivery-diagnostics-and-worker-recovery).

Aceste rezultate sunt istorice, pentru etapele indicate. Modificarea README-ului și a imaginii nu reprezintă o rerulare a tuturor testelor sau o verificare live a producției.

## Navigarea în administrarea restaurantului

**Pregătește restaurantul** este primul acces sub selectorul locației. Pașii pentru program, zone de livrare, meniu, identitate și personal includ editorii funcționali: salvezi și continui în același ghid. „Locații” permite alegerea unei locații accesibile și continuarea configurării în wizard.

- **Operațiuni:** prezentare generală, comenzi, rezervări, mese și QR, ecranul bucătăriei.
- **Administrarea restaurantului:** meniu, personal, locații, identitate, program și livrare.
- **Conectări:** integrări și webhooks.

Paginile separate folosesc aceleași date și salvări. Personalul fără drepturi de administrare vede doar accesul operațional permis. Vezi [ADMIN_NAVIGATION.md](docs/ADMIN_NAVIGATION.md).

## Adresa din pin și harta de livrare

1. În **Pregătește restaurantul → Nume și adresă**, apasă pe hartă sau mută pinul la intrarea restaurantului.
2. Apasă **Preia adresa de la pin**. Strada și numărul, localitatea, țara și codul poștal disponibile completează formularul, fără să mute pinul. Interfața indică datele care mai trebuie verificate sau completate.
3. Verifică adresa și apasă **Salvează pasul** pentru a păstra adresa și poziția împreună.
4. În **Setări → Stabilește ritmul → Zone de livrare**, adaugă zona: harta pornește cu zoom pe pinul salvat. Folosește **Centrează pe restaurant** pentru a reveni după deplasarea hărții.
5. Dacă există doar o adresă completă salvată, aceasta este căutată o singură dată la deschiderea setărilor. Dacă sunt mai multe rezultate, alege adresa corectă. Această căutare centrează harta; nu salvează automat un pin în setup.
6. Desenează poligonul și apasă **Salvează setările**. Zonele deja desenate rămân vizibile integral la redeschidere; butonul de centrare revine la restaurant.

Interfața este disponibilă în română și engleză. Detalii despre furnizor, configurare și limite: [RESTAURANT_LOCATION.md](docs/RESTAURANT_LOCATION.md).

## Stack și pornire locală

Laravel 12 / PHP 8.4 / MariaDB / Redis / Horizon / Reverb / Sanctum, Vue 3 / Inertia 3 / TypeScript / PrimeVue 4 / Tailwind 4. Aplicația este un monolit modular, cu logică de business în Domain Actions.

```sh
php scripts/configure-local.php
composer install
npm ci
docker compose up -d --wait
php artisan migrate
php artisan db:seed
php artisan storage:link
npm run build
php artisan serve
```

Folosește Linux/WSL pentru Composer și Horizon. Rulează separat Horizon, Reverb și scheduler-ul conform [instrucțiunilor de deployment](docs/DEPLOYMENT.md). Credențialele demo sunt generate local și salvate numai în storage privat ignorat de Git.

Pentru Resend, configurează `MAIL_MAILER=resend`, `RESEND_API_KEY`, un `MAIL_FROM_ADDRESS` verificat și `ADMIN_EMAIL`. Cheile și adresele private se păstrează în configurația mediului, nu în repository. Dezvoltarea locală folosește implicit mailerul log.

## Documentație

- [Arhitectură și decizii](docs/ARCHITECTURE.md)
- [Specificația inițială](docs/MASTER_PROMPT.md)
- [Schema bazei de date](docs/DATABASE_SCHEMA.md)
- [Contracte API](docs/API.md) și [OpenAPI](docs/openapi.json)
- [Securitate](docs/SECURITY.md)
- [Personal, branding, limbi și webhooks](docs/STAFF_BRANDING_LOCALIZATION_WEBHOOKS.md)
- [Setup-ul restaurantului și analiza de referință](docs/RESTAURANT_SETUP.md)
- [Catalogul culinar](docs/CUISINES.md) și [creditele fotografiilor](docs/CUISINE_PHOTO_CREDITS.md)
- [Rezervări, QR și zone de livrare](docs/RESERVATIONS_DINING_DELIVERY.md)
- [Funcționalități și limite](docs/MVP_SCOPE.md)
- [Istoricul verificărilor și progresului](docs/CODEX_PROGRESS.md)
- [Instalare locală și Forge](docs/DEPLOYMENT.md)
- [Localizarea restaurantului după adresă](docs/RESTAURANT_LOCATION.md)
- [Pagina de prezentare](docs/LANDING_PAGE.md) și [prompturile imaginilor](docs/assets/landing-images.prompt.md)
- [Changelog](CHANGELOG.md)
- [Inventarul fișierelor implementate](docs/IMPLEMENTED_FILES.txt)

Fiecare commit nou crește versiunea patch și sincronizează fișierele de versiune. Push-ul, tag-urile și deployment-ul urmează autorizarea explicită a utilizatorului; istoricul deja publicat nu este rescris.
