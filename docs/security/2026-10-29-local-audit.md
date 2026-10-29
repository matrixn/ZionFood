# Audit local de securitate — 0.0.33

Data: 29 octombrie 2026. Bază verificată: `663701f0cf33e1b042358426501cc97cd9230591` (0.0.32). Scan Codex Security: `63de4e0c-5437-4b3e-921f-f305cc5f1ce8`. Cele trei constatări de mai jos au severitate **medie**, cu remedieri și teste de regresie în 0.0.33.

## Domeniu și limite

Revizie independentă a surselor relevante pentru autentificare, autorizare, separarea organizațiilor/locațiilor, API, comenzi și prețuri, QR/tracking, încărcări, importuri, emailuri și conexiuni externe. Dovezile păstrate de evaluatori acoperă 199 de fișiere distincte citite integral; inventarul inițial conținea 573 de fișiere. Aceasta este o evaluare parțială a surselor, nu o certificare exhaustivă a întregii aplicații.

Reproducerile au folosit SQLite izolat, cozi simulate și un proces PHP cu memorie limitată. Verificările Chromium au folosit o bază SQLite temporară separată. Nu s-au scanat domeniile publice, nu s-au folosit conturi de producție și nu s-au trimis emailuri sau cereri reale către GloriaFood/Resend. Configurația Forge/Nginx/PHP-FPM, firewallul, ACL-urile, TLS/WSS, livrarea în inbox și concurența în infrastructura de producție nu sunt validate de aceste rezultate. Dependențele generate nu au fost auditate integral și nu s-a executat un audit online al vulnerabilităților din pachete.

## Constatări și remedieri

### 1. Manager restricționat putea modifica starea comenzilor — CWE-863

Un manager verificat al aceleiași organizații, cu `orders=none` sau `orders=view`, putea modifica `accepting_orders`, `pickup_enabled` și `delivery_enabled` prin ruta de setări. Ruta echivalentă din setup verifica deja permisiunea de modificare a comenzilor. Proba locală a confirmat persistența tuturor celor trei modificări. Nu s-a demonstrat acces la altă organizație sau la conținutul comenzilor.

Remediere: setările se salvează prin `SaveLocationSettings`, comparând valorile normalizate cu locația proaspăt blocată în tranzacție. `AuthorizeOrderingChanges` este comun setărilor și setup-ului. Doar schimbările efective ale comutatoarelor cer `orders=manage`; modificarea cutoff-ului cere separat `tables=manage`. Interfața blochează comutatoarele fără acces. Proprietarul își păstrează accesul, iar managerii pot salva metadate/program/zone când comutatoarele sunt neschimbate. Acestea rămân intenționat setări administrative; nu s-a introdus o nouă politică de permisiuni pentru întregul formular.

### 2. Raportul GloriaFood putea epuiza memoria — CWE-400

Un meniu JSON valid de 1.327.243 de octeți solicita 40.000 de coșuri sintetice, cu 16.000.000 de alegeri repetate din grupurile moștenite. Normalizarea a terminat la aproximativ 90 MiB; generarea dovezilor a epuizat plafonul de 256 MiB al procesului izolat. Accesul necesita administrare și modificare de meniu; limitele individuale și throttlingul existent nu limitau produsul dimensiunilor.

Remediere: verificare cumulată incrementală în timpul normalizării, apoi înainte de alocarea dovezilor și înainte de tranzacția care scrie snapshoturi. Plafoane: 10.000 de coșuri, 50.000 de alegeri selectate cumulate și 8 MiB de buget conservator pentru dovezile JSON, inclusiv escaparea numelor. Se refuză explicit cu `import_complexity_limit`, fără trunchiere silențioasă și fără publicare. Fișierul, jobul API și revalidarea folosesc aceeași limită. Meniurile obișnuite păstrează dovezi complete. Aceeași probă de 1,33 MB, sub același plafon PHP de 256 MiB, produce acum refuz controlat și cod de ieșire 0. Nu s-a executat un test de încărcare concurentă.

### 3. Fluxurile publice de cont puteau ocupa cota comună de email — CWE-400

Opt înregistrări distincte de la aceeași adresă IP au creat opt conturi și opt joburi de email fără limită de admitere. Codul livra ulterior în aceeași cotă folosită de resetări, invitații și rezervări. Epuizarea efectivă a cotei Resend nu a fost încercată; consecința asupra cotei comune a fost urmărită în surse. Cotele finale limitau deja consumul total, iar emailurile de autentificare expiră în aproximativ 60 de minute.

Remediere: admitere comună înaintea efectelor pentru înregistrare, cerere de resetare și retrimiterea verificării; implicit 10 cereri/oră/IP și 30 de cereri în 24h consecutive cumulate, inclusiv între IP-uri diferite. Joburile de cont au separat maximum 30 de destinatari/24h. Ambele plafoane zilnice sunt limitate suplimentar la jumătate din cea mai mică capacitate zilnică cunoscută a aplicației/furnizorului, inclusiv limita locală din admin și limita raportată de Resend. Astfel, sursele publice de cont nu pot ocupa singure întreaga cotă cunoscută. Joburile istorice cu sursă necunoscută `legacy` sunt tratate conservator în aceeași subcotă; invitațiile și rezervările clasificate nu o consumă.

Limitele folosesc cache Redis comun și blocare atomică în afara testelor; indisponibilitatea cache-ului nu permite admiterea. Excesul HTTP întoarce 429 cu `Retry-After` înainte de creare cont/notificare/job. Excesul din worker este amânat cu motivul `account_mail_quota`, vizibil în admin, păstrând expirarea existentă. Cotele generale de 140/24h și cota zilnică Resend rămân active.

Compromis operațional explicit: cererile admise, inclusiv cele invalide sau fără email de trimis, consumă conservator bugetul de solicitări. Un abuz distribuit poate ocupa bugetul fluxurilor de cont; celelalte emailuri își păstrează capacitate. Ajustarea valorilor se face prin `MAIL_ACCOUNT_DAILY_LIMIT` și `MAIL_ACCOUNT_REQUESTS_PER_HOUR`, cu plafonul de siguranță descris mai sus. Bugetul nu garantează capacitate dacă alte aplicații sau alte surse consumă cota furnizorului și nu reprezintă o limită lunară separată.

## Ruta publică de test email — alegere acceptată

Utilizatorul a ales explicit să păstreze GET/POST `/test-email` publice temporar. Adresa administratorului a fost eliminată din HTML și din proprietățile Inertia; interfața primește doar starea configurării. Destinatarul rămâne exclusiv cel din configurarea serverului, fără posibilitate de înlocuire prin formular. Limita comună de trei teste/oră rămâne.

Risc rezidual acceptat: orice vizitator poate consuma această cotă de diagnostic și poate genera mesaje către administrator. Acesta nu este raportat ca o lipsă accidentală de autentificare. Eliminarea/restrângerea ulterioară a rutei rămâne o decizie a proprietarului.

## Verificare și reproducere

`tests/Feature/SecurityRegressionTest.php` acoperă toate comutatoarele în ambele direcții, matrici fără acces/doar vizualizare, valori boolean de formular, model învechit, proprietar/manager/organizație străină, import multiplicativ, buget de coșuri și nume escapate, fișier/job API, admitere combinată/distribuită, resetarea ferestrei, limitele locale/raportate ale furnizorului și amânarea înainte de rezervarea cotei comune sau apelarea transportului. Testele existente de import verifică și publicarea, regulile/prețurile reale din fixture și reimportul.

`tests/e2e/security-regression.spec.ts` verifică interfața managerului/proprietarului, editorii integrați și CSRF prin HTTP real. Pregătește o bază locală izolată, rulează `php artisan zionfood:browser-fixtures --security` și setează `ZF_SECURITY_FIXTURE` la fișierul privat `storage/app/private/browser-security.json`; apoi rulează Playwright cu `ZF_E2E_URL` pentru serverul local. Nu comite fișierul privat sau baza de test. Testul este omis dacă variabila fixture lipsește. `email-test.spec.ts` verifică RO/EN și mobil fără trimitere externă.

Rezultatele finale ale tuturor verificărilor sunt consemnate în [CODEX_PROGRESS](../CODEX_PROGRESS.md). Raportul canonic al scanului păstrează revizia vulnerabilă inițială; acest document descrie remedierile din release. Dovezile statice ale evaluatorului și validarea dinamică ulterioară sunt etape distincte.
