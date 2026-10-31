# doliTrace – indicazioni per Claude

Modulo esterno Dolibarr per la tracciabilità agricola: piani colturali (piani di produzione), operazioni colturali, raccolte e lotti di produzione, quaderno di campagna. Fa parte della suite doliFarm insieme a doliFarm (fascicolo aziendale e anagrafiche), doliAgroPass e doliGas.

## Regole fondamentali

- **Il core di Dolibarr non si modifica mai.** Tutto passa da classi, hook, trigger e file del modulo.
- **Il nome della cartella è `dolitrace`, tutto minuscolo.** Clonare con `git clone git@github.com:doliFarm/doliTrace.git dolitrace`: su Linux una cartella `doliTrace` impedisce l'attivazione.
- **Ambiti:** doliTrace gestisce tracciabilità, piani colturali (compreso il piano colturale AGEA) e quaderno di campagna. Fascicolo aziendale, appezzamenti, colture, macchine e agrofarmaci sono anagrafiche di doliFarm: doliTrace le usa ma non ne crea le tabelle.
- **doliTrace dipende da doliFarm** (`$this->depends` contiene `modDolifarm`): le sue tabelle hanno chiavi esterne verso `llx_dolifarm_plot`, `llx_dolifarm_crops` e `llx_dolifarm_farmdossier`.
- **Magazzino solo tramite le classi di Dolibarr.** Raccolte e trattamenti muovono il magazzino con `MouvementStock` (`reception()`, `livraison()`, `setOrigin()`), **mai con SQL diretto** su `llx_product_stock`, `llx_product_batch`, `llx_product_lot`, `llx_stock_mouvement`. Il lotto si crea con il primo movimento che lo usa.

## Magazzino e lotti

- Il magazzino di produzione viene trovato o creato all'attivazione (`modDolitrace::initProductionWarehouse()`) e il suo id è salvato in `DOLITRACE_WAREHOUSE_PROD`. Non cercarlo per `ref`: `Entrepot::create()` salva il label nella colonna `ref`.
- La validazione del piano colturale crea, se manca, il prodotto della coltura e salva sul piano `fk_output_product` e il codice lotto `batch_output` (`AA-COLT-RIFPIANO`); non crea giacenze né lotti.
- Raccolta (trigger in `core/triggers/`): validazione e riapertura caricano la quantità nel magazzino di produzione con il lotto della raccolta o del piano; ritorno a bozza, annullamento ed eliminazione di una raccolta validata la scaricano dallo stesso magazzino e lotto. Il saldo dei movimenti già fatti (`origintype = 'dolitraceharvest@dolitrace'`) evita doppi movimenti. Un errore di magazzino blocca il cambio di stato.

## Ambienti e compatibilità

| | WS (Windows, sviluppo) | VPS (Linux, Docker, test) |
|---|---|---|
| Dolibarr | 24.0.2 | 24.0.2 |
| PHP | 7.4.26 | 8.4 / 8.5 |
| Database | MariaDB 10.6.5 | MySQL 8.x |

- Requisito minimo: **Dolibarr 23**, come doliFarm.
- Il codice deve funzionare **da PHP 7.4 a PHP 8.5**:
  - niente sintassi solo PHP 8: `match`, `?->`, argomenti nominati, union types, enum, `readonly`, promozione dei parametri nel costruttore, `str_contains()`/`str_starts_with()`;
  - niente deprecazioni PHP 8.x: dichiarare sempre le proprietà delle classi, non passare `null` alle funzioni native, nullable esplicito (`?Tipo $x = null`), niente `curl_close()`.
- SQL compatibile con **MariaDB 10.6 e MySQL 8**. Nomi di tabelle e colonne in minuscolo.
- Verifica della sintassi sulla WS: `C:\dolibarr\bin\php\php7.4.26\php.exe -l <file>`.

## Struttura

- `core/modules/modDolitrace.class.php`: descrittore (numero 500003, permessi, menu, `init()` con il magazzino di produzione).
- `class/`: oggetti `CommonObject` generati da ModuleBuilder: `DoliTraceCropplan` (piano colturale), `DoliTraceOperation` (operazione colturale), `DoliTraceHarvest` (raccolta).
- Pagine nella radice: `dolitrace<oggetto>_{list,card,agenda,contact,document,note}.php`, più `dolitracecropplan_harvests.php` e `dolitracecropplan_operations.php`.
- `core/triggers/`: validazione del piano e movimenti di magazzino delle raccolte.
- `lib/`: intestazioni delle schede. `admin/`: setup ed extrafields. `ajax/`: endpoint. `pwa/`: app per il campo.
- `langs/{it_IT,en_US,fr_FR}/dolitrace.lang`: le chiavi sono in inglese; il testo italiano va solo nei valori.

## Database e SQL

- Dolibarr esegue `sql/llx_*.sql`, poi `sql/llx_*.key.sql`, poi `sql/data*.sql`, poi `sql/update_*.sql` **a ogni attivazione del modulo**. Gli script devono essere **idempotenti e non distruttivi**: `CREATE TABLE IF NOT EXISTS`, niente `DROP TABLE`, `INSERT` che non duplicano.
- I `.key.sql` girano prima degli `update_*.sql`: gli indici su colonne aggiunte da uno script di aggiornamento vanno nello stesso script di aggiornamento.
- Gli indici unici sugli oggetti multi-entity devono includere `entity`.
- I dati demo stanno in `sql/demo/` e si importano a mano: non vanno mai caricati in automatico (un file `sql/llx_*.sql` viene eseguito a ogni attivazione).
- Prefisso: `llx_` nei file `.sql`; nel codice `MAIN_DB_PREFIX` o `$this->db->prefix()`.

## Convenzioni di codice

- Seguire lo stile del codice Dolibarr circostante: tab, `GETPOST()`/`GETPOSTINT()` per l'input, `$this->db->escape()` e cast `(int)` nelle query, `dol_syslog()` per i log.
- Permessi con `$user->hasRight('dolitrace', 'oggetto', 'azione')`, non `$user->rights->...`.
- Costanti con `getDolGlobalString()`/`getDolGlobalInt()` e `dolibarr_set_const()`, non `$conf->global->...`.
- Ogni pagina controlla permessi ed entity (`restrictedArea()` o equivalente).
- Niente file di debug, niente scritture in `/tmp`, niente log a livello WARNING per messaggi informativi.

## Git e deploy

- Lavoro su `develop`; `main` è ciò che va sulla VPS. I tag sono firmati: li crea l'utente.
- Deploy sulla VPS: `git pull` manuale nel container `farm-dolibarr` (`/var/www/html/custom/dolitrace`), poi disattivare e riattivare il modulo. Log: `docker exec farm-dolibarr tail -f /var/www/documents/dolibarr.log`.
- La specifica del quaderno di campagna è in `../dolifarm/docs/` (esclusa da git).
