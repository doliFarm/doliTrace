# CHANGELOG MODULE DOLITRACE FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## 1.1.0

Registro dei trattamenti e delle fertilizzazioni (Reg. UE 2023/564) e magazzino delle raccolte con le classi di Dolibarr. Richiede doliFarm con operatori, fertilizzanti, visibilità per commerciale e azienda unica (versione successiva alla 1.2.0). Dopo l'aggiornamento disattivare e riattivare il modulo per creare tabelle, dizionari e menu.

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
