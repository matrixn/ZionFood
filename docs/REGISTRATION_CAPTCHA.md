# Verificarea rapidă ZionFood — 0.0.39

Înregistrarea obișnuită include o verificare proprie, în română implicit și engleză opțional, prezentată ca „Un pas și ești gata” / „One quick step”. Vizitatorul citește patru cifre dintr-o imagine PNG generată local, le introduce prin tastatură sau butoane și verifică răspunsul. Rezultatul apare animat; butonul de creare a contului devine disponibil după confirmare. Imaginea este generată dinamic prin extensia GD deja obligatorie, fără fonturi sau servicii CAPTCHA externe.

Există o alternativă text cu o adunare simplă, utilizabilă cu cititor de ecran și tastatură. Câmpul are etichetă explicită și ajutor contextual, mesajele folosesc regiuni de status/alertă, iar animația respectă preferința pentru mișcare redusă. Schimbarea limbii înnoiește întrebarea și confirmarea, astfel încât instrucțiunile să nu rămână în limba precedentă. Eșecul sau expirarea permit o verificare nouă, fără a șterge datele contului. Nu se pretinde o certificare formală de accesibilitate.

## Fluxul verificat pe server

- `POST /register/challenge`: mod `visual` sau `accessible`; vizitator neautentificat, CSRF și throttling. Returnează ID aleatoriu, PNG ca data URI ori întrebarea text și limita câmpului. Codul corect nu este livrat ca text sau câmp ascuns în modul vizual.
- `POST /register/challenge/verify`: ID și `captcha_answer` ca șir de cifre. Serverul validează răspunsul, îl consumă și emite un `proof` aleatoriu de 64 de caractere. O încercare greșită consumă verificarea respectivă.
- `POST /register`: `CreateNewUser` validează întâi datele normale, apoi consumă `captcha_proof` și verifică bugetul existent pentru emailuri de cont înainte de a crea utilizatorul sau notificarea. Erorile obișnuite ale formularului nu consumă confirmarea; refolosirea unei confirmări deja utilizate este respinsă.

Verificările și confirmările sunt legate de un nonce din sesiunea existentă și expiră după cinci minute. Cache-ul conține HMAC-ul răspunsului, nu codul în clar; cheile folosesc hashuri ale tokenurilor. O blocare atomică protejează consumarea unică. O altă sesiune nu poate folosi sau invalida verificarea/confirmarea vizitatorului. Maximum trei verificări și trei confirmări rămân active per sesiune, pentru a permite câteva taburi fără creștere nelimitată. Valorile sensibile sunt excluse din inputul reflasheat la validare.

Răspunsurile JSON sunt `no-store, private` și `noindex, nofollow`. Nu sunt adăugate cookie-uri de urmărire, profilare de dispozitiv sau cereri către furnizori externi. În afara testelor este obligatoriu un cache Redis comun, configurat prin `REGISTRATION_CAPTCHA_CACHE_STORE`; implicit se folosește `MAIL_RATE_LIMIT_STORE`, apoi `redis`. Lipsa cache-ului nu permite înregistrarea fără verificare.

Endpointurile de emitere/verificare împart limite de 10 cereri/minut și 40/oră/IP. Trimiterea formularului de înregistrare are separat 10 încercări/minut și 60/oră/IP. Un formular invalid sau fără CAPTCHA valid nu mai consumă bugetul global de admitere pentru emailurile de cont; o înregistrare validă sau o invitație validată continuă să îl consume. Resetările și retrimiterile verificării emailului își păstrează controalele existente.

## Invitații și limite

O invitație validată pe server dovedește accesul la linkul trimis de restaurant; acest flux păstrează emailul precompletat și accesul direct al personalului, fără CAPTCHA suplimentar. Câmpurile trimise de client precum `invited` sau `captcha_verified` nu pot activa excepția. Invitațiile revocate, expirate sau cu alt email rămân respinse. Înregistrarea obișnuită păstrează verificarea emailului și onboardingul proprietarului.

Acesta este un strat de protecție împotriva automatizării simple, nu o garanție împotriva roboților avansați. OCR poate citi codul, iar o automatizare țintită poate rezolva alternativa text. De aceea se păstrează limitarea cererilor, bugetele de email și verificarea adresei. Nu există un comutator public sau ocolire prin JavaScript pentru verificarea serverului.

## Validare locală

Pest acoperă PNG-ul, codul vizual exact, alternativa text RO/EN, lipsa/dovada falsă, tipurile de răspuns, expirarea, reutilizarea, sesiuni străine, erori de formular, numărul de provocări active, rate limiting, bugetul emailurilor și eșecul cache-ului. Testele invitațiilor confirmă fluxul existent.

Playwright: `ZF_CAPTCHA_E2E=1` activează `tests/e2e/registration-captcha.spec.ts`. Folosește exclusiv o bază locală izolată și `MAIL_MAILER=array`; testul creează un cont nou. Testul de invitație poate folosi `ZF_INVITATION_FIXTURE` pentru o invitație privată de test. Verificările finale și limitele sunt în [CODEX_PROGRESS.md](CODEX_PROGRESS.md). Nu s-au verificat Redis concurent sau comportamentul Forge în producție și nu s-au trimis emailuri reale.
