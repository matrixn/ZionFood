# ZionFood Rewards

## Configurează o campanie

1. Selectează locația și deschide **ZionFood Rewards → Campanii → Campanie nouă**.
2. Alege denumirea, metoda de acumulare și produsele eligibile. Orice combinație din lista eligibilă contribuie la prag.
3. Setează câte unități trebuie cumpărate și ce produs se oferă, cu cantitatea dorită. De exemplu, la cinci cafele cumpărate, clientul primește un cappuccino.
4. Opțional, setează prima și ultima zi. Ambele sunt incluse, în fusul orar al locației. Activează acumularea și salvează.

Poți derula campanii în paralel, inclusiv cu metode diferite. O comandă online poate conta o dată în fiecare campanie aplicabilă. După salvare poți schimba numele, perioada și starea. Pentru alte produse, cantități sau metode creezi o campanie nouă, astfel încât progresul deja înregistrat să păstreze aceleași condiții. Recompensele câștigate se pot preda și după pauză sau încheierea perioadei.

## Cele trei metode

- **QR-ul clientului.** Clientul deschide cardul digital și generează un cod temporar. În **Operare la tejghea**, personalul scanează QR-ul cu camera sau introduce codul afișat, alege campania și confirmă unitățile plătite pe baza bonului.
- **QR temporar al locației.** Personalul generează un QR pentru o anumită campanie. Un client îl poate folosi în cinci minute pentru a trimite o solicitare. Echipa verifică bonul și confirmă solicitarea în următoarele 30 de minute. Simplul scan nu înregistrează achiziția.
- **Comandă online finalizată.** Clientul își atașează explicit cardul în checkout. Campania trebuie să permită acumularea de la plasare până la finalizare, conform perioadei și ultimei activări. Se numără unitățile eligibile cu preț pozitiv din instantaneul comenzii. Unitățile complet gratuite din promoția aplicată nu contribuie. Reducerile parțiale permit acumularea pentru unitățile care au rămas cu preț. Comenzile respinse sau anulate nu generează progres.

Clientul vede cardul în meniul digital când există cel puțin o campanie activă. Cardul se păstrează în browser pentru locația și domeniul folosite la creare. Pentru predare, clientul prezintă un QR nou. Personalul alege recompensa și confirmă predarea produselor. Serverul validează disponibilitatea și înregistrează o singură predare pentru acel cod.

## Operare și date

Administratorul sau managerul restaurantului configurează campaniile. Personalul are nevoie de permisiunea de modificare a comenzilor pentru locația respectivă. Accesul de platformă rămâne separat. Interfața de lucru are traduceri RO, EN, IT, ES și NL. Cardul public folosește RO și EN, la fel ca meniul digital.

Cardul nu cere date de contact. Secretul cardului rămâne în browser, iar serverul păstrează amprenta sa. Codurile QR au cinci minute de valabilitate inițială, sunt legate de locație și se consumă la confirmare sau predare. Referințele bonurilor sunt normalizate, apoi stocate ca amprente per campanie pentru deduplicare. Progresul și predările sunt înregistrate tranzacțional, inclusiv atunci când achiziția provine din finalizarea unei comenzi. Jurnalul păstrează cantitățile și persoana care a confirmat operațiunea. Jobul programat curăță codurile expirate în fiecare oră.

Deploy-ul obișnuit trebuie să ruleze migrarea pentru Rewards înainte să servească noul cod și să aibă scheduler-ul activ. Camera necesită acces HTTPS sau localhost și acordul browserului. Introducerea manuală rămâne disponibilă pentru codul clientului. Prima versiune păstrează cardul per browser și predarea prin personal. Recuperarea între dispozitive, revendicarea în checkout și integrarea cu portofele native sunt funcții separate.
