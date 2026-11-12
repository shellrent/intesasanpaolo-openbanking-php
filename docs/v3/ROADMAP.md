# Roadmap – versione 3.0.0

Piano di lavoro della riscrittura 3.0 dell'SDK. È la **fonte di verità sullo stato delle task**: leggila prima di iniziare e aggiornala alla fine di ogni task.

> Documento temporaneo: viene eliminato al rilascio della 3.0 (T8).

- Branch di lavoro: `3.x`, creato da `main` (D-01). Push al termine di ogni task, nessun tag fino al rilascio.
- Si lavora **una task alla volta**, e ogni task parte solo dopo l'approvazione esplicita del maintainer.
- Documenti collegati: [DECISIONS.md](DECISIONS.md) (decisioni e questioni aperte), [FINDINGS.md](FINDINGS.md) (problemi da risolvere).

## Stato

| Task | Titolo | Stato |
|---|---|---|
| T0 | Branch: `master` → `main`, creazione di `3.x` | ✅ completata |
| T1 | Documentazione per agent e file di progetto | ✅ completata |
| T2 | Tooling e dipendenze | ✅ completata |
| T3 | Core: configurazione, trasporto, date, eccezioni | ✅ completata |
| T4 | API Conti | ✅ completata |
| T5 | API SCT Instant | ⏳ da fare |
| T6 | API SCT (bonifico ordinario) | ⏳ da fare |
| T7 | API IBAN e raggiungibilità | ⏳ da fare |
| T8 | Rilascio 3.0.0 | ⏳ da fare |
| T9 | Migrazione del gestionale (repo esterno) | ⏳ da fare |

## Definizione di "completata"

Una task è completata quando:
- i test sono verdi (da T2: anche analisi statica e formattazione);
- la documentazione permanente toccata dalla task è aggiornata;
- `CHANGELOG.md` (sezione Unreleased) riporta le modifiche visibili a chi usa la libreria;
- i finding collegati in `FINDINGS.md` hanno lo stato aggiornato;
- le nuove decisioni sono registrate in `DECISIONS.md`;
- c'è una voce nel registro di avanzamento in fondo a questo file;
- il lavoro è committato e `3.x` è stata pushata.

## Task

### T0 · Branch ✅
- Rinominare `master` in `main` su GitHub e nel clone locale.
- Eliminare il branch `php80`, già incluso in `main`.
- Creare `3.x` da `main`.

### T1 · Documentazione per agent e file di progetto ✅
- `AGENTS.md` e `CLAUDE.md`.
- Area temporanea `docs/v3/` (roadmap, finding, decisioni).
- Documentazione permanente: `docs/README.md`, `docs/api-coverage.md`, `docs/api-quirks.md`, `docs/specs-reference.md`.
- README riscritto; `LICENSE.md`, `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `.editorconfig`, `.gitattributes`, `.gitignore`.
- Line ending normalizzati a LF.

### T2 · Tooling e dipendenze ✅
- `composer.json`:
  - `php: ^8.3` e `guzzlehttp/guzzle: ^7.9 || ^8.0`, senza `php-http/guzzle7-adapter` (D-05, D-06);
  - script `test`, `analyse`, `format`, `ci`.
- Dipendenze di sviluppo: PHPUnit 12.5, PHPStan 2 e formatter (O-02); file di configurazione `.dist`.
- Decidere se tenere `composer.lock` nel repo: rimosso (D-16).
- GitHub Actions: PHP 8.3, 8.4 e 8.5, con dipendenze sia minime sia massime; `composer validate`, analisi statica, test.
- Rendere verde la suite attuale come punto di partenza: test riscritti in `tests/Unit/Legacy/` senza Faker né phpdotenv, più `ClientTest` che verifica i quirk di trasporto con il `MockHandler` di Guzzle.
- Verificare la compatibilità con Guzzle 8: verificata, minimi alzati a `^7.15.2 || ^8.0.1` per le advisory di sicurezza; incompatibilità con l'estensione PECL `psr` documentata (D-20).
- Finding: F-07, F-26, F-28 (in parte), F-34 (in parte).

### T3 · Core ✅
- Configurazione: `ClientConfig`, `Environment`, `MutualTlsCertificate`, con mTLS obbligatorio (D-23).
- Trasporto:
  - timeout configurabili;
  - lettura del JSON con eccezioni e controllo di `success`;
  - mappatura completa degli errori 4xx, 5xx e di rete, con `X-ISP-Transaction-ID`;
  - header di risposta disponibili al chiamante;
  - `Content-Type` sulle GET SCT (Q-04) senza stato condiviso;
  - client Guzzle iniettabile.
- `Auth/`: `TokenProvider` con margine sulla scadenza e `TokenStore` per condividere il token tra istanze (D-21); nessun logger integrato (D-22).
- `Paginator`: limite di pagine e riscrittura dei link della sandbox (Q-44).
- `DateParser` con formati espliciti (Q-09–Q-11) e `Payload` per leggere i campi in modo tollerante.
- Eccezioni con un'interfaccia comune.
- Test con `MockHandler`.
- Documentazione: `docs/architecture.md`, `docs/dates-and-formats.md`, `docs/testing.md`; prima bozza di `UPGRADE-3.0.md`.
- Finding: F-01 (trasporto), F-02 (parser), F-04, F-05, F-06, F-17 (base), F-19, F-20, F-21, F-22, F-25 (in parte), F-29, F-30, F-31 (in parte).
- Non incluso, come previsto: la facade `IntesaSanPaoloClient` arriva in T4 insieme alla prima API; il codice 2.x resta invariato.

### T4 · API Conti ✅
- Saldo con data opzionale.
- Movimenti di fine giornata e intraday: paginazione, movimenti non contabilizzati, contatori, `valueDate` nullo.
- DTO readonly; fixture ricavate dalle spec ed eventuali risposte reali anonimizzate.
- Finding: F-12, F-15, F-18, F-23.
- Fatto anche: facade `IntesaSanPaoloClient` con `accounts()`; client 2.x spostato in `Legacy\` senza i metodi dei conti, con i relativi model e test 2.x eliminati (D-30).

### T5 · API SCT Instant
- Simulazione con esito VoP (Q-37).
- Esecuzione con resubmit automatico quando c'è un `resubmitId`.
- Stato e lista.
- Stati tolleranti (D-11).
- Richiesta validata: campi obbligatori, importo, lunghezze, caratteri SEPA.
- Quirk `endToEndId`/`siaCode` tra Live e sandbox (Q-34).
- Documentazione: `docs/payment-flows.md`, parte SCT Instant.
- Finding: F-01, F-03, F-10, F-11 (in parte), F-24, F-25 (in parte), F-32, F-36.

### T6 · API SCT (bonifico ordinario)
- Simulazione: VoP, `fees` o `commissioni`, `categoryPurpose` configurabile, data di esecuzione opzionale, EUR di default.
- Esecuzione: `ACTC`, `VRFY`, date `d/m/Y`, data e ora di revoca.
- Stato con tutti i filtri.
- Lista completa.
- Revoca.
- Documentazione: `docs/payment-flows.md`, parte SCT.
- Finding: F-08, F-09, F-10, F-11, F-13, F-14, F-16, F-24, F-25 (in parte), F-32, F-33, F-36.

### T7 · API IBAN e raggiungibilità
- Verifica sintattica (Q-19).
- `chargeabilityMulti` (Q-20, Q-47).
- Raggiungibilità SCT Instant (Q-18).
- Chiarire "Verifica esistenza IBAN" (O-06): riscaricare la spec, poi verificare in sandbox o con il supporto.
- Documentazione: `docs/adding-an-endpoint.md`, usando queste API come esempio.
- Finding: F-35.

### T8 · Rilascio 3.0.0
- `UPGRADE-3.0.md` definitiva, `CHANGELOG.md` e README con gli esempi d'uso.
- Test di integrazione in sandbox, opzionali: usano credenziali e certificato locali, mai nel repo.
- Revisione finale del codice.
- Spostare i contenuti ancora validi di `docs/v3/` ed eliminare la cartella.
- PR da `3.x` verso `main` (serve 1 approvazione), tag `3.0.0`.

### T9 · Migrazione del gestionale (repo esterno)
Migrazione alla 3.0, provabile prima del tag con `3.x-dev`, e correzioni lato gestionale (gestione dell'esito VoP, stati `ACTC` e `VRFY`, resubmit). Si traccia nel repository del gestionale (D-15).

## Registro di avanzamento

| Data | Task | Esito |
|---|---|---|
| 2026-11-12 | T0 | `master` rinominato in `main` (protezione mantenuta), eliminato `php80`, creato `3.x` |
| 2026-11-12 | T1 | Documentazione per agent, area `docs/v3/`, documentazione permanente (copertura, quirk, spec), file di progetto, line ending a LF |
| 2026-11-12 | T2 | PHP ^8.3, Guzzle `^7.15.2 \|\| ^8.0.1` senza adapter; PHPUnit 12.5, PHPStan max, Pint; CI GitHub Actions (8.3/8.4/8.5 × dipendenze minime e massime); test 2.x riscritti e verdi; nullable espliciti; `composer.lock` rimosso; trovata l'incompatibilità tra Guzzle 8 e l'estensione PECL `psr` (D-20) |
| 2026-11-12 | T3 | Core 3.0: `Config/`, `Auth/` (`TokenStore`), `Http/` (`Sender`, `Transport`, `Paginator`, riscrittura dei link della sandbox), `Support/` (`DateParser`, `Payload`), `Exception/`; mTLS obbligatorio, timeout, nessun retry, errori 4xx/5xx/rete mappati, transaction ID esposto; 97 test (anche con Guzzle 7 + estensione `psr`); documentazione: architettura, date, test, bozza di `UPGRADE-3.0.md`; nuovo finding F-36 |
| 2026-11-12 | T4 | Facade `IntesaSanPaoloClient` con `accounts()`: `balance()`, `transactions()`, `todayTransactions()`; DTO `Balance`, `Transaction`, `TransactionList` (`isComplete()`, transaction ID per pagina); enum `TransactionStatus` (trait `FromApiValue`); date ISO sempre accettate; `date` mantenuta tra le pagine di fine giornata; client 2.x in `Legacy\`; fixture in `tests/Fixtures/`; 115 test; README con esempi, `UPGRADE-3.0.md` per i conti |
