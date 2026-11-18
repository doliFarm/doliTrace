# CHANGELOG MODULE DOLITRACE FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.5.9 (in sviluppo)

Regole di coerenza e app di campo 1.7.0. Dopo l'aggiornamento **riattivare doliTrace** (colonne di `llx_dolitrace_pwa_receipt`, dizionario delle fasi fenologiche); richiede doliFarm 1.5.14.

- Operazioni, trattamenti e raccolte devono avere la data nel periodo del piano di produzione (web, app, import); il piano non accetta una fine prima dell'inizio né date che lascerebbero fuori le sue registrazioni.
- Piano di produzione: fascicoli e appezzamenti della sola azienda scelta, appezzamenti del fascicolo scelto; controllo al salvataggio.
- Trattamento: fascicolo, piano, operatore e macchina dell'azienda scelta. Operazione: la macchina è un elenco delle macchine dell'azienda del piano (prima era un numero).
- Dizionario "Fasi fenologiche (BBCH)", scala generale, creato e completato dal modulo all'attivazione; fase dell'operazione da elenco.
- App 1.7.0 (API 2): lingua del telefono; "doliFarm" al posto di "Dolibarr"; la "i" diventa X e chiude l'info; meteo che non esce dal riquadro; durata calcolata da inizio e fine; date limitate al piano; tab Appezzamenti / Piani di produzione; ultime 5 registrazioni per tipo con "Vedi tutte" (anche i trattamenti); icone per aggiornamento non riuscito e registrazioni non inviate; modifica e cancellazione delle proprie bozze inviate dall'app finché restano bozze; banner di installazione; "Come funziona senza rete" nell'info; Indietro che torna al livello superiore e conferma prima di lasciare un modulo non salvato; con azienda singola niente nome dell'azienda nelle righe; macchine e operatori solo dell'azienda del piano, avviso per operatore senza patentino.

## 1.5.8 (2026-11-18)

Menu. Dopo l'aggiornamento **riattivare doliTrace** (menu).

- Correzione: la voce "App di campo" non compariva sotto "Aiutami" (Dolibarr cerca la voce madre solo tra quelle con posizione più bassa).

## 1.5.7 (2026-11-18)

App di campo 1.6.1 e menu. Dopo l'aggiornamento **riattivare doliTrace** (menu); richiede doliFarm 1.5.11.

- "App di campo" sotto "Aiutami", nella stessa sezione in fondo al menu a sinistra.
- App di campo 1.6.1: quando torna in primo piano con la rete (al massimo ogni 10 minuti) cerca una nuova versione, senza doverla chiudere e riaprire; il messaggio "Nuova versione dell'app pronta" compare una volta sola.
- Installazione su Android: con "Installa" Android 15 e 16 possono bloccare l'app ("App non sicura bloccata... versione precedente di Android"), anche da Chrome; è un problema noto di Chrome. Le istruzioni indicano menu ⋮ → "Aggiungi a schermata Home" → "Crea scorciatoia".

## 1.5.6 (2026-11-18)

App di campo 1.6.0. Dopo l'aggiornamento non serve riattivare il modulo: l'app si aggiorna aprendola con la rete.

- Scheda dell'appezzamento: scorrendo a sinistra o a destra (o con i tasti ‹ › in alto) si passa all'appezzamento successivo o precedente, nell'ordine dell'elenco e con la ricerca attiva; "Indietro" torna all'elenco.
- Piani di produzione dell'appezzamento uno alla volta ("Piano 1 di 3"): scorrendo sul piano o con i tasti ‹ › si passa agli altri piani dello stesso appezzamento.
- Pagina **Info** (icona in alto a destra): utente, azienda, server e ultimo aggiornamento, uscita da Dolibarr e cancellazione dei dati del telefono; indicazioni sulla privacy; spazio per le personalizzazioni (nessuna per ora); informazioni e contatti di doliFarm, versione e licenza.
- La risposta di sessione dell'API dell'app riporta anche il nome dell'azienda e l'indirizzo di logout.

## 1.5.5 (2026-11-18)

Menu e app di campo 1.4.1. Dopo l'aggiornamento **riattivare doliTrace** (menu); richiede doliFarm 1.5.10.

- "App di campo" e "Aiutami" in fondo al menu a sinistra, dopo un separatore; QdC secondo nel top menu.
- App di campo 1.4.1: la scansione legge anche il QR della scheda e del PDF dell'appezzamento (`?id=`), compila il campo Riferimento e apre la scheda; se l'appezzamento non è nei dati del telefono mostra il link alla scheda in Dolibarr. Lo stesso QR viene letto una volta sola.
- Installazione su Android: usare Chrome. Da Samsung Internet Android segnala l'app come "creata per una versione precedente di Android"; l'app lo ricorda quando è aperta da Samsung Internet.

## 1.5.4 (2026-11-18)

Trattamenti, foto e meteo dall'app di campo, che ora si chiama doliFarm (la 1.5.3 non è stata pubblicata ed è inclusa qui). Dopo l'aggiornamento **riattivare doliTrace**: registra il nuovo permesso e lo script `sql/update_dolitrace_draft_ref.sql` sistema le bozze e l'indice dei documenti esistenti. Poi assegnare "Usare l'app di campo: bozze di trattamenti" a chi registra i trattamenti dal telefono.

- App di campo 1.4.0: **meteo di doliField sul telefono**, scaricato con gli altri dati e consultabile senza rete.
  - Scheda dell'appezzamento: previsione dei prossimi 3 giorni (minima e massima, pioggia, vento) e finestre adatte ai trattamenti nelle 48 ore, secondo le soglie di doliField; fonte e ora della previsione, con un avviso se i dati hanno più di un giorno.
  - Moduli di operazione e trattamento: meteo previsto all'ora indicata (temperatura, vento, pioggia durante e nelle ore dopo). Per i trattamenti fitosanitari, gli avvisi di vento, pioggia e temperatura oltre le soglie, già sul telefono.
  - Il registro continua a riportare il meteo copiato alla convalida per l'ora della registrazione, con il dato migliore disponibile in quel momento (stazione, rianalisi ERA5-Land, previsione).
- App di campo 1.3.1: si chiama **doliFarm** (nome sotto l'icona e nel titolo) e si apre con una pagina con il logo di doliFarm, il nome dell'app e la versione. Un tocco la chiude prima. Su Android il nome cambia quando Chrome aggiorna l'app installata; su iPhone serve reinstallarla (Condividi > Aggiungi alla schermata Home).


- App di campo 1.3.0: trattamenti fitosanitari e fertilizzazioni dal campo, come bozze sull'appezzamento del piano di produzione. Si indicano superficie, BBCH, operatore (proposto quello collegato all'utente), macchina, volume d'acqua e uno o più prodotti della stessa botte, ognuno con dose e unità, quantità totale (calcolata se lasciata vuota) e lotto.
  - Prodotti: per i fitosanitari quelli autorizzati sulla coltura del piano, con "Mostra anche i prodotti non autorizzati". Avversità proposte dagli impieghi dell'etichetta, oppure scelte dal dizionario.
  - Lotti: quelli del magazzino mezzi tecnici dell'azienda, con la giacenza al momento dello scaricamento. Il lotto resta modificabile in ufficio e la convalida controlla la giacenza vera.
  - Avvisi sul telefono, anche senza rete: impiego non autorizzato su coltura o avversità, dose fuori etichetta o oltre la massima, prodotto revocato, sospeso o scaduto, non ammesso in biologico su un appezzamento bio, patentino o controllo della macchina scaduti, giacenza del lotto insufficiente. Non bloccano il salvataggio. Dopo l'invio l'app mostra gli avvisi completi del registro (gruppi FRAC, carenza, azoto, meteo).
- Nuovo permesso "Usare l'app di campo: bozze di trattamenti", separato da quello di operazioni e raccolte: il trattamento entra nel registro. Chi ha la scrittura del registro può usare l'app anche senza.
- Pagina "App di campo": elenco delle funzioni aggiornato; agli amministratori, i permessi da assegnare.
- App: l'avviso "Nuova versione pronta" compare anche quando l'aggiornamento è stato scaricato mentre l'app era aperta in un'altra pagina.
- App di campo 1.2.0 (foto): fino a 5 foto per operazione, raccolta o trattamento, dalla fotocamera o dalla galleria. Le foto vengono ridotte sul telefono (lato lungo 1600 px, JPEG sotto 1 MB) e restano nel telefono finché non vengono inviate. Si inviano dopo la loro registrazione e arrivano nella scheda Documenti della bozza. Inviata due volte, una foto non si duplica. Con foto in attesa non si possono cancellare i dati del telefono.
- Server: nuova chiamata `photo` dell'endpoint dell'app. Accetta solo JPEG fino a 4 MB, solo su registrazioni inviate dall'app dallo stesso utente, al massimo 5 per registrazione.
- Bozze di operazioni e raccolte: ricevono il riferimento provvisorio `(PROVid)` alla creazione, come gli altri oggetti di Dolibarr. Prima restavano senza riferimento e i loro documenti finivano in una cartella comune. Anche la copia di una registrazione nasce come bozza `(PROVid)` e non più come "Copy_Of_...".
- Convalida di piani di produzione, operazioni, raccolte e trattamenti: l'indice dei documenti (`ecm_files`) restava sul percorso `(PROVid)` e i file non comparivano più nella scheda Documenti. Il modello di ModuleBuilder cercava il percorso senza la cartella del modulo. Ora l'indice segue la cartella rinominata.

## 1.5.2 (2026-11-18)

Help per ruolo (richiede doliFarm 1.5.9 per la separazione; con una versione precedente compaiono i testi per gli utenti). Dopo l'aggiornamento non serve riattivare il modulo.

- Testi separati per ruolo: unità della raccolta (dizionario "Unità" solo agli amministratori), nessun magazzino mezzi tecnici, pagina pubblica non attiva, app di campo senza https (agli utenti "avvisare l'amministratore").

## 1.5.1 (2026-11-18)

Correzioni di sicurezza e dell'app di campo. Dopo l'aggiornamento non serve riattivare il modulo.

- Sicurezza: liste e schede di piani di produzione, operazioni e raccolte (scheda, note, documenti, contatti, agenda) non controllavano i permessi di doliTrace: il controllo generato da ModuleBuilder era legato alla costante `DOLITRACE_ENABLE_PERMISSION_CHECK`, mai impostata, e senza di essa ogni utente collegato poteva leggere, modificare, convalidare e cancellare. Ora i permessi di lettura, scrittura e cancellazione valgono sempre (restano i filtri per azienda). Verificare che gli utenti che usano queste pagine abbiano i permessi di doliTrace.
- App di campo 1.1.1: quantità della raccolta e superficie dell'operazione in un campo di testo con la virgola o il punto come separatore decimale; in alcuni browser il campo numerico scartava la virgola e 12,5 diventava 125 senza avviso. Un valore non valido dà l'errore del campo.
- App di campo: le unità con simboli (mm³, µl) comparivano con il codice HTML (`mm&sup3;`).

## 1.5.0 (2026-11-18)

App di campo (PWA) per il telefono: consultazione senza rete, QR dei cartelli, registrazione di operazioni e raccolte come bozze. Dopo l'aggiornamento **riattivare doliTrace** (nuova tabella, nuovo permesso, nuova voce di menu) e assegnare il permesso "Usare l'app di campo" a chi registra dal telefono. L'app richiede https.

- App di campo (PWA), fase 2 del piano: endpoint JSON `ajax/pwa.php` con la sessione di Dolibarr. `session` (utente, permessi, token), `data` (appezzamenti, piani di produzione aperti, ultime operazioni e raccolte, tempi di carenza in corso, tipi di operazione, unità, macchine; solo le aziende visibili all'utente), `send` (POST con token: bozze di operazioni e raccolte, fino a 50 per invio). Ogni registrazione ha un identificativo generato dal telefono: se arriva due volte si restituisce quella già creata (tabella `llx_dolitrace_pwa_receipt`). Le bozze riportano nella nota privata chi le ha registrate e quando.
- App di campo (PWA), fase P1, riscritta da zero in `pwa/` al posto del prototipo: JavaScript senza framework e senza librerie da CDN, icone locali, service worker (l'app parte senza rete), dati in IndexedDB per giorni senza rete. Elenco degli appezzamenti con ricerca; scheda dell'appezzamento con piani aperti, carenze in corso, ultime operazioni e raccolte; lettura del QR dei cartelli con la fotocamera (lettore del browser su Android, libreria jsQR 1.4.0 in copia locale su iPhone, licenza Apache 2.0 in `pwa/assets/vendor/jsqr/`) o scrivendo il riferimento; appezzamento più vicino con il GPS; avvisi per dati vecchi e login scaduto (`pwa/login.php` riporta all'app dopo il login); con un altro utente sullo stesso telefono i dati del precedente vengono cancellati. Italiano, inglese, francese.
- App di campo, fase P2: dalla scheda dell'appezzamento "+ Operazione" e "+ Raccolta" su ogni piano aperto (solo con il permesso). La registrazione si salva nel telefono e parte subito se c'è rete, altrimenti quando torna (anche dopo giorni: tentativo ogni minuto e al ritorno nell'app); in Dolibarr arriva come bozza. Avviso sempre visibile con quante registrazioni aspettano e da quanti giorni; pagina "Da inviare" con "Invia ora", le registrazioni rifiutate con il motivo (si correggono o si eliminano) e le ultime inviate con il link alla bozza. Le registrazioni di un altro utente rimaste sul telefono non vengono cancellate: l'app chiede di accedere con quell'utente; "Cancella i dati" è bloccato finché ci sono registrazioni da inviare. Controlli sul telefono uguali a quelli del server.
- Nuova pagina "App di campo" nel menu di doliTrace: cosa fa, come installarla su Android e iPhone, QR dell'indirizzo dell'app.
- Nuovo permesso "Usare l'app di campo" (`dolitracepwa`/`use`): crea solo bozze di operazioni e raccolte sulle aziende visibili, senza modifica né cancellazione. Chi ha già la scrittura di operazioni o raccolte può usare l'app anche senza.
- Sicurezza: tolti `ajax/dolitraceoperation.php`, `ajax/dolitraceharvest.php` e `ajax/dolitracecropplan.php`, generati da ModuleBuilder e mai usati: con il permesso di scrittura permettevano di cambiare qualsiasi campo di operazioni, raccolte e piani (anche convalidati e di altre aziende) senza token CSRF.

## 1.4.2 (2026-11-18)

Correzioni della protezione CSRF. Dopo l'aggiornamento non serve riattivare il modulo.

- Etichette QR delle raccolte: "Crea il PDF" mostrava l'errore della protezione CSRF di Dolibarr ("Token not provided") invece del PDF; il modulo ora invia il token.
- Costi del piano di produzione: la matita per modificare una riga del preventivo era bloccata dalla stessa protezione.

## 1.4.1 (2026-11-17)

Correzioni trovate nei test di accettazione sul Docker. Dopo l'aggiornamento **riattivare doliTrace** (normalizzazione delle unità delle raccolte).

- Ricerca "in tutti i campi" negli elenchi di piani di produzione, raccolte, operazioni e registro: dava un errore SQL (operazioni: pagina vuota). Ora cerca in riferimento e titolo, nelle raccolte anche in lotto e UUID di tracciabilità, nel registro nel riferimento; con un solo risultato apre la scheda.
- Unità delle raccolte scritte a mano: all'attivazione diventano quelle della lista anche quando cambiano solo maiuscole e minuscole ("Kg" → "kg", "LT" → "L", "t" → "T"); prima il confronto del database, che non distingue le maiuscole, le lasciava com'erano.
- Inglese: titolo della home "Field notebooks" (era "Home Dolitrace").

## 1.4.0 (2026-11-17)

Tracciabilità, fase T2: pagina pubblica dal QR in etichetta. Dopo il `git pull` va riattivato il modulo (campi aggiuntivi "Storia").

- Pagina pubblica `public/trace.php?u=<UUID>`, senza login, leggera e per telefono, in italiano, inglese o francese secondo il browser. Mostra le sole raccolte convalidate: prodotto e foto, azienda (nome commerciale, logo, comune, sito, certificato bio), data di raccolta, lotto, codice breve, coltura, appezzamento e comune, stato bio, riepilogo dei trattamenti (numero, ammessi in bio, giorni dall'ultimo trattamento alla raccolta), note pubbliche della raccolta. Se il lotto viene da più raccolte della stessa azienda, le mostra tutte. Mai clienti, prezzi, quantità, operatori, note private, dati catastali o del fascicolo. Risposta identica (404) per UUID inesistente, raccolta non convalidata o pagina non attiva; nessun cookie.
- Setup di doliTrace, sezione "Pagina pubblica di tracciabilità": attivazione (spenta per default), indirizzo pubblico di Dolibarr per i QR, elenco dei trattamenti con data e sostanza attiva (spento: solo riepilogo), mappa OpenStreetMap dell'appezzamento (spenta: solo nome e comune).
- Campi aggiuntivi "Storia (pagina pubblica)" su soggetti terzi e prodotti, pubblicati nella pagina.
- Scheda della raccolta: il QR apre la pagina pubblica quando è attiva (altrimenti la pagina interna), con il collegamento "Pagina pubblica"; nuovo pulsante "Etichette QR" sulle raccolte convalidate.
- Modulo di tracciabilità per il sito aziendale: nel setup il codice HTML da copiare (con anteprima e scelta dell'azienda se ce ne sono più di una). Il visitatore scrive il codice breve o il lotto in etichetta e si apre la pagina pubblica; se più prodotti corrispondono, la pagina li elenca. La pagina pubblica accetta ora anche codice breve e lotto (`?c=`) e ha un campo di ricerca.
- Etichette QR in PDF su fogli A4 adesivi (3 x 8 da 70 x 37 mm, 2 x 5 da 105 x 57 mm, 2 x 4 da 105 x 74 mm): prodotto, lotto, data di raccolta, azienda, codice breve; si possono saltare le etichette già usate del primo foglio.

## 1.3.3 (2026-11-17)

Condizioni meteo da doliField 0.3.0 (solo con doliField attivo). Basta il `git pull`, senza riattivare il modulo: nessuna colonna nuova.

- Registrazioni dei trattamenti su superfici: la scheda in bozza mostra le condizioni meteo dell'appezzamento del piano di produzione nel periodo del trattamento (temperatura, umidità, vento, pioggia durante e nelle ore dopo, fonte) con il pulsante "Usa nella registrazione"; alla convalida, se il campo Condizioni meteo è vuoto, vi si copia il testo, che entra nell'hash della registrazione.
- Avvisi meteo sui trattamenti fitosanitari in bozza: vento, pioggia durante e dopo il trattamento e temperatura oltre le soglie della configurazione di doliField.
- Operazioni: alla convalida, se il campo Condizioni meteo è vuoto, vi si copiano le condizioni dell'appezzamento nel periodo dell'operazione (testo breve, 128 caratteri).

## 1.3.2 (2026-11-06)

Scarico del seme dalle semine. Dopo l'aggiornamento disattivare e riattivare il modulo (colonne nuove sulle operazioni).

- Semine e impianti convalidati scaricano il seme (prodotto, lotto e quantità dell'operazione, nell'unità del prodotto) dal magazzino dei mezzi tecnici dell'azienda, alla data dell'operazione. Il ritorno in bozza, l'annullamento e la cancellazione lo stornano alla stessa data; una modifica di prodotto, lotto, quantità o piano storna e scarica di nuovo. Un errore di magazzino non blocca l'operazione: il seme resta "da regolarizzare" con il motivo e il pulsante Regolarizza magazzino.
- Registri bio: i movimenti delle semine hanno l'origine "Semina o impianto"; la scheda Sementi mostra l'acquisto del lotto (ordine o ricevimento, fornitore e certificato bio) e lo stato del magazzino.
- Più piani di produzione in bozza di seguito (prima il secondo dava "riferimento già esistente").

## 1.3.1

Correzione della gestione di una o più aziende. Richiede doliFarm 1.5.1. Dopo l'aggiornamento non serve riattivare il modulo (nessuna modifica al database).

- Filtri azienda di export, import, registri bio e ispezioni: solo le aziende gestite, fissa in modalità azienda singola.

## 1.3.0

Tracciabilità delle raccolte, trattamenti dal piano di produzione, ispezioni bio, lotti e costi del piano. Richiede doliFarm 1.5.0 o successiva: aggiornare doliFarm prima di doliTrace, poi disattivare e riattivare doliTrace (tabelle delle ispezioni e del preventivo, colonne nuove su trattamenti, operazioni e piani, menu). Requisito minimo PHP 8.2 e Dolibarr 23; provato su PHP 8.4, MySQL 8.4 e Dolibarr 24.0.2.

### Tracciabilità
- UUID proprio per ogni raccolta (le raccolte esistenti lo ricevono all'attivazione), QR corretto, pagina "Tracciabilità" con la storia a monte e a valle e scheda PDF.
- Riferimenti numerati correttamente (prima tutti -0001).

### Piano di produzione
- Azienda scelta solo tra le aziende gestite, fissa in modalità azienda singola.
- Tab "Registro trattamenti" con creazione e import dal piano; un piano per registrazione; tab "Storia" con PDF e CSV.
- Tab "Costi": preventivo (operazioni previste con costi standard per ettaro) e consuntivo (operazioni, trattamenti con prodotti al prezzo medio ponderato, raccolte), scostamento, costo per ettaro e per unità raccolta; totali del piano aggiornati automaticamente.
- Operazioni: superficie e costi calcolati da costi standard e prezzo medio dei prodotti se vuoti, pulsante "Ricalcola costi".

### Registro dei trattamenti
- Fitosanitari cercati anche nel catalogo del Ministero; prodotti ammessi in biologico in evidenza sulle superfici bio.

### Raccolte e lotti
- Lotto nuovo (codice proposto dal piano) o aggiunta a un lotto aperto dello stesso prodotto; controllo dello stato bio del lotto alla convalida.
- Unità da elenco (dizionario delle unità di Dolibarr, con q, hl, cassetta, bin) e conversione nell'unità del magazzino.

### Biologico
- Ispezioni bio (anche dei trasformatori): organismo dal dizionario di doliFarm, ispettore, campioni, esito, verbale; la chiusura congela operazioni, raccolte e periodi bio fino alla data dell'ispezione e salva una copia in sola lettura dei registri (JSON con SHA-256 e PDF); riapertura con permesso dedicato e motivo.
- Menu QdC: sezione "Biologico" e voce "Tracciabilità".

### Altro
- Home del modulo con dati reali (piani in corso, ultime raccolte e operazioni) e controllo dei permessi.
- Traduzioni italiane, inglesi e francesi complete.
- Dati demo: la raccolta si inserisce una sola volta.

## 1.2.0

Produzione biologica nel quaderno di campagna, dati AGEA e registri bio. Richiede doliFarm 1.4.0 o successiva: aggiornare doliFarm prima di doliTrace, poi disattivare e riattivare doliTrace (colonne, dizionari e menu nuovi). Requisito minimo PHP 8.2 e Dolibarr 23; provato su PHP 8.4, MySQL 8.4 e Dolibarr 24.0.2.

### Registro dei trattamenti
- Stato bio dell'appezzamento copiato sulle superfici alla convalida; avvisi su appezzamenti bio o in conversione per agrofarmaci e fertilizzanti non ammessi o da verificare (conferma esplicita e proposta di nuova conversione), rame oltre 28 kg/ha in 7 anni (setup).
- Giustificazione dei fitosanitari (dizionario dei tipi e riferimento).
- Avvisi sugli usi autorizzati (coltura, avversità, dose, intervallo di sicurezza, numero di trattamenti per coltura), sulle resistenze (FRAC/IRAC) e sui prodotti revocati o sospesi.
- Avversità cercate su tutto il database EPPO, aggiunte al dizionario se mancano.
- Dati AGEA: metodo di applicazione, controllo dell'irroratrice, sementi in migliaia di semi, quantità AGEA, operatori e macchine, azoto dei fertilizzanti liquidi in zona vulnerabile; quantità di magazzino nell'unità del prodotto.
- Export JSON in formato 1.4: eventi colturali e irrigazioni, siti di stoccaggio con le giacenze, operatori, macchine, sezione `organic`.

### Operazioni colturali e raccolte
- Eventi colturali e irrigazioni del quaderno, data di fine e fase BBCH; semina con stato bio del materiale e deroga BDSB.
- Raccolte: stato bio del prodotto alla convalida, suffisso -BIO o -INCONV nel lotto, sottomagazzino dedicato allo stato, avvisi per magazzino di un altro stato e lotti misti; carico nel magazzino dei prodotti del fascicolo.

### Registri bio (nuova pagina QdC → Raccolte → Registri bio, sola lettura)
- Registro di carico e scarico per prodotto e lotto con giacenza iniziale e progressiva, origine, controparte e suo certificato bio.
- Bilancio di massa per coltura e stato con confronto con l'anno precedente; riepilogo per appezzamento al posto del PAP.
- Sementi e deroghe, mezzi tecnici usati con l'esito bio, trattamenti, acquisti, raccolte, vendite.
- Fascicolo bio annuale in PDF con tutte le sezioni; PDF e CSV di ogni registro. Formato provvisorio, da adattare alle richieste dell'organismo di controllo.

### Altro
- Visibilità per commerciale negli elenchi a discesa e filtro "Commerciale" nelle liste; gruppo "Quaderno di campagna" nella Vista globale della home.
- Licenza AGPL-3.0 o successiva, titolare Luigi Grillo - doliFarm.com.
- Correzioni dai test di accettazione: import (duplicati), PDF del registro, fusi orari, scheda informazioni delle operazioni.

## 1.1.0

Registro dei trattamenti e delle fertilizzazioni (Reg. UE 2023/564) e magazzino delle raccolte con le classi di Dolibarr. Richiede doliFarm 1.3.0 o successiva (operatori, fertilizzanti, visibilità per commerciale e azienda unica): aggiornare doliFarm prima di doliTrace. Dopo l'aggiornamento disattivare e riattivare il modulo per creare tabelle, dizionari e menu.

### Registro dei trattamenti e delle fertilizzazioni
- Nuovo oggetto Registrazione (riferimento REG): trattamento fitosanitario o fertilizzazione, su superfici, spazi chiusi o sementi; azienda e fascicolo, date di inizio e fine, motivo, operatore, contoterzista, macchina, volume d'acqua, condizioni meteo.
- Superfici trattate solo dai piani colturali validati in corso alla data (appezzamento, coltura con codice EPPO, particella, zona vulnerabile ai nitrati, fase BBCH), oppure fabbricati o lotti di semente.
- Prodotti: agrofarmaci con avversità (dizionario con codici EPPO) o fertilizzanti con titolo di N, P2O5 e K2O; dose con unità (dizionario "Unità di dose"), quantità totale calcolata da dose e superficie o indicata a mano, azoto distribuito per ettaro, lotto.
- Convalida: controlli dei dati obbligatori, copia nella registrazione dei dati delle anagrafiche, impronta SHA-256 del contenuto e verifica di integrità, storico delle azioni. Le registrazioni convalidate non si modificano: si correggono con una nuova versione (riferimento -V2, -V3...) o si annullano con un motivo. Le registrazioni fatte oltre 30 giorni dal trattamento sono segnate come tardive.
- Magazzino: alla convalida i prodotti escono dal magazzino dei mezzi tecnici dell'azienda con MouvementStock; se lo scarico non riesce la riga resta "da regolarizzare" senza bloccare la registrazione; storno su correzione e annullamento.
- Avvisi, che non bloccano la convalida: patentino o controllo funzionale scaduti, registrazione tardiva, autorizzazione del prodotto scaduta, dose oltre il massimo, raccolte entro l'intervallo di sicurezza, azoto di origine zootecnica oltre il limite annuo in zona vulnerabile (setup, 170 kg N/ha per default).
- Esportazione per azienda e periodo: PDF del registro, CSV (una riga per prodotto e superficie) e JSON con verifica di integrità.
- Importazione da CSV con le colonne dell'esportazione: verifica del file, poi creazione delle registrazioni in bozza; le registrazioni già presenti sono scartate.
- Lista e schede rispettano la visibilità delle aziende per commerciale di doliFarm.
- Nuovi permessi: lettura, creazione e modifica, eliminazione delle bozze, convalida.

### Raccolte e piani colturali
- Raccolte: carico nel magazzino di produzione alla validazione e alla riapertura, scarico al ritorno in bozza, all'annullamento e all'eliminazione, con MouvementStock e origine collegata; niente più SQL diretto sulle giacenze. Un errore di magazzino blocca il cambio di stato.
- Validazione del piano colturale: prodotto e codice lotto calcolati dal riferimento definitivo.
- Magazzino di produzione salvato per id (DOLITRACE_WAREHOUSE_PROD): l'attivazione non tenta più di ricrearlo.

### Altro
- Script SQL idempotenti; i dati demo passano in sql/demo/ e si importano a mano.
- Pagina di setup: tolti i parametri di esempio di ModuleBuilder.
- Requisito minimo Dolibarr 23, come doliFarm.

## 1.0

Initial version
