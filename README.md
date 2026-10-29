# ZionFood

![ZionFood — comenzi, rezervări și administrarea restaurantului](docs/assets/zionfood-hero-ai.png)

**Versiune: 0.0.28** · Platformă SaaS pentru restaurante · Română implicit, engleză opțional.

ZionFood reunește configurarea restaurantului, meniul public, comenzile, rezervările, comenzile la masă prin QR și operațiunile din bucătărie. Datele sunt organizate pe **Organizație → Restaurant → Locație**, cu acces separat pentru administrarea platformei și echipa restaurantului.

Imaginea este o ilustrație generată cu AI, nu o captură din aplicație. [Prompt și proveniență](docs/assets/zionfood-hero-ai.prompt.md).

## Cum se citește istoricul

- `[x]` = funcționalitate implementată sau etapă finalizată în cod.
- `[ ]` = funcționalitate rămasă de implementat sau verificare operațională încă necesară.

Bifele nu certifică automat un deployment în producție. Rezultatele și limitele verificărilor sunt consemnate în [CODEX_PROGRESS.md](docs/CODEX_PROGRESS.md).

## Istoricul proiectului

### 29 octombrie 2026 — disponibilitate vizibilă înainte de comandă · 0.0.28

- [x] Meniul restaurantului și pagina mesei afișează de la început dacă locația este deschisă, ora închiderii și următoarea deschidere disponibilă.
- [x] Anunț în ultima oră de comandă, cu minutele rămase și ora-limită; la QR respectă limita configurată înainte de închidere, la ridicare/livrare folosește închiderea locației.
- [x] **Setări generale → Disponibilitatea comenzilor online**: protecție activă implicit, editabilă și în pregătirea restaurantului. În afara programului blochează adăugarea în coș/plasarea; dacă serviciul mese/QR este activ, cere și o masă activă cu meniul deschis de personal.
- [x] Protecția poate fi dezactivată pentru a permite doar pregătirea coșului în afara programului sau cu toate mesele închise. Plasarea comenzii rămâne blocată până când locația este disponibilă. Anunțul de locație închisă rămâne afișat; oprirea manuală, serviciile, abonamentul și regulile QR rămân obligatorii.
- [x] Actualizare automată la 15 secunde și la revenirea în pagină; ora-limită este urmărită și local. Modalele explică de ce adăugarea/plasarea este blocată, iar coșul și comenzile deja trimise sunt păstrate.

[Reguli, limite și operare](docs/RESERVATIONS_DINING_DELIVERY.md).

### 28 octombrie 2026 — prima pagină pregătită pentru căutare și migrare GloriaFood · 0.0.27

- [x] Conținutul primei pagini este livrat direct în HTML, inclusiv fără JavaScript, din aceeași componentă Vue; buildul generează variante RO/EN pentru vizitatori și utilizatori autentificați.
- [x] Adrese stabile `/` în română și `/en` în engleză, canonical, hreflang reciproc și selector cu preferință persistentă; metadate disponibile înainte de JavaScript și sincronizate după încărcare.
- [x] Titluri și descrieri pentru motoarele de căutare, Open Graph/Twitter, date structurate Organization/WebSite/WebPage/Service și favicon ZionFood.
- [x] Sitemap XML cu ambele limbi și robots.txt din configurarea domeniului public; paginile private/de diagnostic au antet noindex. Localhost, staging dezactivat și buildurile SEO lipsă/vechi nu sunt indexabile.
- [x] Secțiune și FAQ despre migrarea GloriaFood: import API/JSON, previzualizare, raport de verificare, publicare controlată și limitele transferului explicate.
- [x] Lighthouse SEO **100/100 pentru RO și EN** în laborator local cu configurare de domeniu public; [condițiile auditului](docs/benchmarks/seo-lighthouse.json).
- [x] Imagini WebP responsive, cu dimensiuni rezervate, prioritate pentru imaginea principală și încărcare întârziată pentru imaginea secundară.
- [ ] Verificarea domeniului public în Google Search Console/Bing Webmaster Tools, trimiterea sitemap-ului și inspecția URL-urilor după deployment; indexarea și clasarea aparțin motoarelor de căutare.

[Configurare SEO și verificări de lansare](docs/LANDING_PAGE.md).

### 27 octombrie 2026 — emailuri în administrarea platformei și cota Resend · 0.0.26

- [x] **Admin → Emailuri și coadă**: evidență paginată, actualizată automat, cu destinatari, subiect, sursă, inițiator, stare, motiv, încercări și următoarea încercare.
- [x] Filtre pentru erori sistem, creare/validare cont, resetare parolă, invitații personal, rezervări și emailuri de test; filtrare și după stare, inițiator, transport, destinatar și interval calendaristic.
- [x] Pauză comună pentru Resend la cota zilnică, lunară sau limita de cereri; emailurile rămân în coadă. Cota zilnică se reia după resetarea UTC, cu verificarea separată a celorlalte limite.
- [x] Prag local Resend de 100 destinatari/zi UTC, configurabil în admin, plus verificarea consumului contului prin API. Limita existentă de 140/24h și suspendarea alertelor după cinci/oră rămân separate.
- [x] Indexarea sigură a emailurilor existente în cozile configurate, metadate private criptate, expirarea linkurilor noi de autentificare și omiterea notificărilor devenite neactuale.
- [x] Confirmarea din interfață înseamnă **acceptat de furnizor**; mesajele locale de dezvoltare au stare separată. [Comportament și limite](docs/EMAIL_DELIVERY.md).
- [ ] Verificarea cu Resend real și cu backlogul Forge după actualizarea normală; istoricul mesajelor trimise anterior activării evidenței nu poate fi reconstruit complet.
- [ ] Confirmări de livrare în inbox, bounce și complaint prin webhooks Resend dedicate.

### 27 octombrie 2026 — recuperare Reverb și retry outbox limitat · 0.0.25

- [x] Limită persistentă de cinci încercări per eveniment, cu pauze între încercări; schedulerul și joburile duplicate respectă aceeași limită.
- [x] Evenimentele vechi cu sute de încercări sunt oprite la migrare, fără ștergerea datelor sau declararea lor ca livrate.
- [x] Reluare controlată prin `php artisan zionfood:outbox-retry EVENT_ULID`, păstrând identificatorul, conținutul și numărul total de încercări.
- [x] `php artisan zionfood:realtime-check` verifică DNS-ul endpointurilor configurate fără publicarea unui eveniment și fără afișarea cheilor.
- [x] Excepțiile Reverb propagate către Laravel/Horizon conțin doar motivul și indicația de diagnostic, fără URL-uri semnate.
- [ ] Pe Forge: aplicarea endpointului intern pregătit `127.0.0.1:8080` și corectarea DNS-ului/proxy-ului pentru `ws.food.zion3d.ro`, după confirmarea modificărilor live. [Instrucțiuni](docs/DEPLOYMENT.md#reverb-dns-and-outbox-recovery-0025).

### 27 octombrie 2026 — mese, afișare și protecția alertelor · 0.0.24

- [x] Fiecare masă afișează numărul comenzilor active și valoarea comenzilor plasate azi, conform fusului locației; anulate/refuzate sunt excluse.
- [x] Detalii cu produsele comandate și marcarea comenzilor pregătite ca servite, cu permisiuni și protecție la modificări simultane.
- [x] Setări generale per locație, și în wizard: formate dată/oră cu sau fără secunde, afișarea telefonului, emailului, site-ului, adresei, descrierii și fotografiilor produselor.
- [x] Întoarcere la meniul restaurantului din confirmarea rezervării, inclusiv pe domenii personalizate.
- [x] Maximum 5 alerte distincte/încercări de trimitere într-o oră; depășirea suspendă alertele până la reactivare în **Platformă → Alerte de erori prin email**. Starea persistă în baza de date; alertele vechi nu sunt retrimise după reactivare. Celelalte emailuri păstrează limita globală 140/24h.
- [x] Diagnostic de broadcast fără secrete și endpoint intern Reverb configurabil separat de conexiunea browserului.
- [x] Cauza Reverb a fost identificată ulterior: DNS pentru `ws.food.zion3d.ro`, independent de cota Resend. După corectarea configurației de către proprietar și repornirea controlată Horizon, un eveniment reîncercat a fost confirmat ca livrat. DNS/WSS pentru conexiunile publice ale browserelor rămâne de verificat separat.

### 27 octombrie 2026 — comenzile active în meniul QR · 0.0.23

- [x] După comanda la masă, clientul rămâne în meniul QR și vede preparatele, cantitățile, opțiunile, totalurile și starea comenzilor.
- [x] Comenzile aceleiași sesiuni sunt comune invitaților mesei, se actualizează automat și rămân vizibile după reîncărcare.
- [x] Comenzile pregătite rămân în listă până sunt finalizate/servite de personal; finalizate, anulate și refuzate dispar individual.
- [x] Oprirea comenzilor/programul închis nu ascund comenzile în curs pentru clienții care au deja acces la sesiune; noii clienți nu primesc istoricul altor sesiuni.

### 27 octombrie 2026 — GloriaFood Migration Center · 0.0.22

- [x] Conectare prin cheia oficială Fetch Menu sau fișier exportat anterior, fără editare manuală JSON.
- [x] Previzualizare separată de meniul public, raport cu erori blocante/avertismente, diferențe, coșuri de referință, JSON și pagină pentru salvare PDF.
- [x] Grupuri moștenite din categorii, modificatori pe dimensiuni, cantități de toppinguri, selecții implicite și reguli minime/maxime aplicate în checkout.
- [x] Alergeni, etichete, nutriție pe produs/dimensiune și restricții pe servicii; asociere explicită a ID-urilor nutriționale fără nume.
- [x] Publicare aprobată pe locație, reimport cu păstrarea editărilor locale, versiuni și revenire protejată, chei temporare criptate și retenție pentru previzualizări.
- [ ] Validare finală cu un restaurant autorizat și cheia sa reală GloriaFood. [Matrice de compatibilitate și utilizare](docs/MIGRATION_CENTER.md).

### 27 octombrie 2026 — corecție Pint în CI · 0.0.21

- [x] Corectate spațiile argumentelor din testul statisticilor restaurantului, conform verificării Linux din GitHub Actions.
- [x] Verificare suplimentară cu un cache Pint nou, pentru a evita rezultate locale mascate de cache.

### 27 octombrie 2026 — statistici, explicații și control compact · 0.0.20

- [x] Prezentare generală cu perioade de 7/30/90 zile, indicatori, grafic și tabel zilnic, top 5 preparate, tipuri de comandă, rezervări, capacitate și top mese după comenzi sau rezervări.
- [x] Contorizare nouă a vizitelor meniului, deduplicate timp de 30 de minute și agregate pe locație/zi, fără IP-uri sau identificatori de vizitatori în baza de date. Nu inventăm vizite istorice.
- [x] Date limitate la locația și permisiunile angajatului. Valoarea comenzilor finalizate, persoanele rezervate și sesiunile QR sunt explicate distinct de încasări, prezență fizică sau persoane unice.
- [x] Control rapid al meselor printr-un selector de sală și două acțiuni compacte; status scurt, explicație în tooltip, check/X în cerc și bordură de 2 px.
- [x] Iconițe info pentru câmpurile formularelor, cu explicații RO/EN, acces prin hover, focus sau atingere, inclusiv în modale.
- [x] Aprobarea generării salvează și închide automat modalul; erorile păstrează modalul deschis, iar succesul este confirmat prin notificare.
- [x] Test real pe Forge cu Groq `openai/gpt-oss-20b`: Feta saganaki, notificare, verificare și aprobare numai pentru secțiunea nutrițională. Cheia nu a fost extrasă sau modificată.
- [ ] Validarea calității estimărilor pe rețete reale și măsurarea ocupării fizice a meselor necesită date și procese suplimentare. [Definițiile statisticilor și limite](docs/RESTAURANT_ANALYTICS.md).

### 27 octombrie 2026 — generare automată și notificări · 0.0.19

- [x] Pagină refăcută în jurul preparatului: căutare, fotografie, categorie, date preluate automat și doi pași simpli, cu acțiunea principală mereu la îndemână.
- [x] Nutriție estimativă din ingrediente și gramajul final pentru **o singură porție**, cu conversie automată la 100 g; fără introducerea manuală a valorilor pe ingredient.
- [x] O singură cerere pentru nutriție, alergeni/etichete, rescriere și limbile alese; rezultat local, corectabil și publicat numai după aprobare.
- [x] Notificări în dreapta sus, inbox persistent și modal cu rezultate, corecturi și aprobare pe secțiuni; actualizare în fundal prin Reverb sau verificare automată, fără refresh manual.
- [x] Eliminat importul manual JSON „Date alimentare din GloriaFood” și vechiul calculator manual. Datele deja publicate sunt păstrate; propunerile de import retrase sunt eliminate la migrare.
- [x] Etichetă explicită de estimare AI pe meniul public; verificări pentru valori imposibile, sursă modificată și accesul angajaților.
- [ ] Verificarea calității estimărilor cu un meniu real și furnizorul configurat; valorile sunt aproximative, nu măsurători de laborator. [Detalii](docs/MENU_CONTENT.md).

### 27 octombrie 2026 — corecție schemă Groq · 0.0.18

- [x] Corectată respingerea `required present but properties is missing`: fiecare operație trimite numai câmpurile proprii, fără obiecte goale pentru traduceri, variante sau opțiuni.
- [x] Structura locală a traducerilor rămâne compatibilă cu editorul și aprobarea propunerilor; fără schimbarea cheii, furnizorului sau datelor restaurantului.
- [x] Teste pe JSON-ul efectiv transmis pentru ambele integrări AI și produse cu structură simplă sau opțiuni lipsă.

### 27 octombrie 2026 — conținut alimentar, traduceri și AI · 0.0.17

- [x] Editor pentru grupele de alergeni, urme posibile și etichete alimentare, cu verificarea combinațiilor contradictorii.
- [x] Traduceri locale, editabile, pentru preparate, categorii, meniuri, ingrediente, variante și opțiuni; româna rămâne sursa, iar traducerile învechite nu se publică.
- [x] Rețete cu cantități, referințe nutriționale la 100 g, randament și porții; calcul determinist, fără estimări AI și fără înlocuirea datelor lipsă cu zero.
- [x] AI configurabil din administrarea generală: Groq implicit sau OpenAI ales explicit, cheie criptată, cotă comună pe 24 h și coadă. Propunerile necesită verificare și aprobare.
- [x] Importul datelor alimentare din JSON GloriaFood Fetch Menu v2 pentru produsul ales, cu raport, proveniență și păstrarea informațiilor fără corespondență sigură.
- [x] Selector public cu steaguri și limbi disponibile, vocabular alimentar/nutrițional local și prețuri/reguli de comandă păstrate.
- [x] Migration Center pentru GloriaFood: client API, import de prețuri/reguli, toppinguri pe variante, reimport, publicare aprobată și rollback protejat. [Configurare și limite](docs/MIGRATION_CENTER.md).

### 27 octombrie 2026 — drepturi pe angajat și controlul meselor · 0.0.16

- [x] Drepturi individuale „Fără acces / Doar vizualizare / Vizualizare și modificare” pentru Comenzi, Rezervări, Mese și QR, Bucătărie și Meniu, la invitație și la editarea angajatului.
- [x] Secțiunile fără acces sunt ascunse; butoanele de modificare lipsesc în modul de vizualizare. Permisiunile sunt verificate și pe server, inclusiv pentru URL-uri/API-uri directe.
- [x] Activare/dezactivare pentru toate mesele și pentru fiecare sală, cu confirmare la închidere și păstrarea codurilor QR permanente.
- [x] Contur verde pentru QR care acceptă comenzi, roșu pentru cele indisponibile, cu motiv și stare manuală separate.
- [x] Programul și limita de oprire înainte de închidere rămân active; clientul și personalul primesc aceeași explicație. Starea meselor se reîmprospătează automat.

### 27 octombrie 2026 — URL-uri, mese, firme și invitații · 0.0.15

- [x] URL public `/restaurant/slug-restaurant`; linkurile vechi `/store/ID` redirecționează. Restaurantele cu mai multe locații au o pagină de selecție.
- [x] Pagină explicativă pentru QR când serviciul sau meniul mesei este închis, în loc de 404 pentru o masă validă.
- [x] Tooltip cu motivul dezactivării comenzilor la masă și acces către activarea serviciului; regenerarea QR nu ocolește setarea locației.
- [x] CUI primul în „Firmă și politici”; preluare gratuită din ANAF, fără cheie API, cu date editabile despre firmă, sediu și situația fiscală.
- [x] Înregistrare din invitație cu email precompletat, fără confirmare suplimentară și fără onboarding de proprietar; acces direct cu rolul și locațiile invitate.

### 26 octombrie 2026 — previzualizare și modale compacte · 0.0.14

- [x] Previzualizarea de distribuire păstrează titlul restaurantului pe primul rând și afișează „Descoperă meniul și alege-ți preparatul preferat!” ca descriere, cu variantă engleză la schimbarea limbii.
- [x] Modale cu lățimi compacte pe desktop, adaptate fiecărui formular, încadrate pe mobil; totalul și butoanele rămân vizibile.

### 26 octombrie 2026 — logo pentru distribuire · 0.0.13

- [x] Imagine PNG separată de 600 × 600 px pentru distribuire, cu logo centrat proporțional pe alb, fără tăierea literelor, inclusiv pentru logo-uri existente.
- [x] Dimensiuni și tip de imagine declarate în metadate; URL nou la schimbarea logo-ului și eliminarea variantei vechi la înlocuire/ștergere.

### 26 octombrie 2026 — experiența meniului digital · 0.0.12

- [x] Distribuire cu numele restaurantului + „Meniu Digital” și logo în metadatele HTML pentru previzualizări.
- [x] Carduri de produs redesenate, fotografii în detalii, porție etichetată și ingrediente discrete în paranteză; titluri fără steluțe sau repetare.
- [x] Coș compact cu fotografii și subtotal/acțiune permanent vizibile.
- [x] Conținut derulabil în modalele lungi; butoanele și totalul checkout-ului rămân vizibile, cu validare minimă înainte de trimitere.
- [x] Adresă de livrare completată automat din pin — stradă, număr, localitate — și editabilă; răspunsurile întârziate nu suprascriu corecturile.
- [x] Panou RO/EN discret pentru alergeni, cu lista extensibilă și referință la Regulamentul (UE) 1169/2011, anexa II.


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

- [x] `0.0.9`: navigare grupată, pregătirea restaurantului prima sub selectorul locației și editori integrați în wizard pentru program, livrare, meniu, identitate, personal și locații — `2a0c23c`.
- [x] `0.0.10`: documentarea editorilor integrați și a comportamentului navigării în wizard.

- [x] `0.0.11`: catalog cu 183 de ingrediente unice din PDF, administrare comună/proprie, marcaje pentru alergeni/congelat, ingrediente ordonate pe produs, gramaj și preț în lei.

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

- [x] 183 de ingrediente unice extrase din meniul PDF: 19 cu marcaj de alergeni, 16 cu marcaj de posibil congelat, 32 de verificat; o denumire ambiguă este ascunsă inițial.
- [x] Catalog comun gestionat de administratorul general și ingrediente proprii fiecărui restaurant, cu adăugare, editare, ascundere și eliminare.
- [x] Ingrediente selectabile și ordonate pe produs, gramaj flexibil (de exemplu 350 g sau 200/250 g), preț și variante introduse în lei, păstrate intern în bani.
- [x] Marcaje independente pentru alergeni și congelat, textul solicitat pe meniul public și referința legală actuală; informațiile rețetelor existente se păstrează după ascunderea/eliminarea ingredientelor.
- [x] Identificatori de catalog cu prefixul `global-`, seed automat la migrare/deployment și reseed fără suprascrierea modificărilor administratorilor; [reguli și audit PDF](docs/INGREDIENTS.md).

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
- [x] Traduceri ale conținutului meniului, salvate local ca propuneri editabile și publicate după verificare; furnizorul AI se configurează separat.

### Operare și verificări de producție

- [ ] Verificarea completă a fluxurilor curente pe Forge, cu DNS/TLS/subdomenii și configurația fiecărei locații.
- [ ] Emailuri reale verificate pentru toate fluxurile relevante și recepția în inbox; acceptarea de către Resend nu dovedește singură recepția în inbox.
- [ ] Alegerea și verificarea furnizorului de hărți pentru traficul de producție, conform limitelor sale de utilizare.
- [ ] QR-uri tipărite pe dispozitive reale, accesibilitate, dark mode și comportamentul touch/sunet/fullscreen.
- [ ] Politici de retenție/ștergere pentru date personale, idempotency și outbox; arhivare și proceduri de backup/restaurare/incidente.
- [ ] Măsurători de capacitate și recuperare după întreruperi pe infrastructura țintă; benchmark-urile locale nu sunt garanții de producție.
- [ ] Eliminarea rutei publice temporare `/test-email` după încheierea diagnosticării.

## Verificări executate

Ingredientele, gramajele și prețurile în lei (`0.0.11`) au trecut **159 de teste backend / 1376 de aserțiuni pe SQLite**, iar cele 7 teste noi au trecut și pe baza MariaDB dedicată (**428 de aserțiuni**). Au trecut 11 teste frontend, TypeScript/build, ESLint și Pint pe Linux. Patru fluxuri de browser au verificat administrarea catalogului comun, ingredientele proprii și rețeta completă, salvarea exactă a prețului, gramajul, păstrarea informațiilor după ascundere, meniul public RO/EN/mobil și regresia wizard-ului. [Reguli, seed și limite](docs/INGREDIENTS.md).

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

| Pas în wizard | Ce poți configura fără să ieși din ghid |
| --- | --- |
| Program de funcționare | Orele de deschidere și limita comenzilor QR înainte de închidere |
| Zone de livrare | Harta, poligoanele sau prefixele poștale, taxele și comenzile minime |
| Meniu și opțiuni | Categorii, produse, variante și modificatori |
| Identitate vizuală | Nume, logo, copertă, culoare și contacte publice |
| Personal și acces | Invitații, roluri și atribuirea locațiilor |
| Locațiile restaurantului | Alegerea și configurarea unei locații existente la care ai drepturi de administrare |

- [x] Salvarea unui editor păstrează pasul curent al wizard-ului.
- [x] Navigarea avertizează când există modificări nesalvate.
- [x] Schimbarea locației din selector păstrează pasul curent al ghidului.
- [x] Navigarea mobilă este derulabilă; pașii și editorii sunt disponibili în RO/EN.

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
