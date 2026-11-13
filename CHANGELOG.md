# Changelog

Le modifiche rilevanti del progetto sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/) e il progetto adotta il [Versionamento Semantico](https://semver.org/lang/it/). Le voci fino alla 2.1.1 sono state ricostruite dalla cronologia git.

## [Unreleased]

## [3.0.0] - 2026-11-13

Riscrittura completa dell'SDK, con modifiche incompatibili con la 2.x: la guida all'aggiornamento è in `UPGRADE-3.0.md`.

### Aggiunto
- Nuova facade `IntesaSanPaoloClient` con le API per area.
- API SCT Instant `sctInstant()`:
  - `simulate()`, con l'**esito della Verification of Payee** (`VopResult`);
  - `execute()`, che **esegue in automatico solo con un match VoP pieno** (altrimenti `VopConfirmationRequiredException` finché non si passa `acceptVopResult: true`) e controlla che la simulazione appartenga al pagamento;
  - `resubmit()` per i pagamenti sospesi;
  - `status()` e `payments()`.
- API SCT (bonifico ordinario) `sct()`:
  - `simulate()`, con esito VoP, commissioni e date previste;
  - `execute()`, con le stesse protezioni dell'Instant;
  - `status()`, per CRO, ID pagamento, end-to-end ID o simulation ID;
  - `payments()` e `revoke()`; un rifiuto della revoca è un esito (`revoked: false`), anche quando la banca lo invia con `success: false`.
- Strumenti IBAN `ibanTools()`:
  - `formalCheck()`, verifica sintattica degli IBAN Intesa Sanpaolo;
  - `verify()`, esistenza dell'IBAN e congruenza con codice fiscale o partita IVA (almeno uno obbligatorio), anche per le banche CBI Globe; un esito negativo è un risultato e non un'eccezione, anche quando arriva con `success: false`.
- `sctInstant()->reachability()`: verifica se un IBAN può ricevere bonifici istantanei.
- Richiesta `SctPayment`: valuta e `categoryPurpose` configurabili, data di esecuzione opzionale (senza data la banca esegue il giorno lavorativo successivo).
- **Importi come stringhe decimali, mai `float`**, in richieste e risposte (`'1250.10'`): formato rigoroso in ingresso, nessun arrotondamento, eccezione sugli importi della banca non esatti, confronto esatto tra simulazione ed esecuzione, JSON inviato identico alla 2.x. `Support\Amount` converte da e verso i centesimi.
- Richiesta `InstantPayment`, validata alla creazione (lunghezze SEPA, IBAN, importo come stringa decimale con al massimo 2 decimali); l'`endToEndId` arriva a 35 caratteri in Live e a 36 in sandbox, come accettano i due ambienti.
- Simulazioni salvabili e ricaricabili (`toArray()`/`fromArray()`).
- Enum `ValidationStatus`, `PaymentStatus` (con `isAccepted()`/`isFinal()`), `InstantStatus` e `VopMatchResult`.
- Helper `SepaText` per pulire i testi dai caratteri non ammessi dalla banca.
- `docs/payment-flows.md`.
- API Conti `accounts()`:
  - `balance()`, con data opzionale;
  - `transactions($date)` e `todayTransactions()`, con tutte le pagine raccolte.
- DTO immutabili `Balance`, `Transaction` e `TransactionList`, con:
  - `isComplete()` per accorgersi di elenchi incompleti;
  - i transaction ID delle risposte;
  - l'enum `TransactionStatus` affiancato dal valore originale.
- Configurazione con `ClientConfig`, `Environment` e `MutualTlsCertificate` (PEM o PKCS#12). **Il certificato mTLS è obbligatorio** e l'ambiente predefinito è la sandbox.
- Timeout configurabili: 10 s per la connessione e 60 s per la richiesta.
- Eccezioni con l'interfaccia comune `OpenBankingException`:
  - `ApiException` per HTTP 4xx/5xx e `success: false`, con gli errori della banca e il transaction ID;
  - `AuthenticationException` per login rifiutato e HTTP 401;
  - `TransportException` per errori di rete e timeout;
  - `UnexpectedResponseException` per risposte non interpretabili;
  - `ConfigurationException` per configurazioni non valide.
- Lettura tollerante dei comportamenti osservati con le API reali: la stringa `"null"` vale come valore assente, anche nell'header VoP, e le date-ora della sandbox con `:` prima dei millisecondi sono accettate.
- Certificato PKCS#12 di SmartHub, che OpenSSL 3 non legge: istruzioni di conversione nel README e suggerimento nel messaggio della `TransportException`.
- Protezioni aggiuntive sui pagamenti: `execute()` rifiuta anche una simulazione fatta per un altro **nome** del creditore, perché l'esito VoP vale per il nome simulato. Le risposte delle esecuzioni si leggono in modo tollerante, così un campo in un formato inatteso non nasconde l'esito né gli identificativi.
- Trasporto: credenziali e certificato vanno solo all'host delle API ISP, anche sui link di paginazione; nessun redirect; credenziali nascoste dagli stack trace (`#[SensitiveParameter]`); testi delle richieste validati come UTF-8; link di paginazione accettati sia come URL sia come `{"next": …}`.
- Test di integrazione opzionali (`composer test:integration`): casi di test della sandbox e verifiche di sola lettura in Live, con blocco delle richieste di scrittura.
- `TokenStore` per condividere il token OAuth2 tra istanze e processi; il token si rinnova 60 s prima della scadenza.
- Core di trasporto 3.0:
  - header di risposta e `X-ISP-Transaction-ID` disponibili;
  - paginazione con protezione da cicli e limite di pagine;
  - correzione dei link di paginazione della sandbox;
  - lettura delle date con formati espliciti.
- Documentazione: `docs/architecture.md`, `docs/dates-and-formats.md`, `docs/testing.md`, `docs/adding-an-endpoint.md`, `docs/decisions.md`, `UPGRADE-3.0.md`.
- Documentazione per agent AI e sviluppatori: `AGENTS.md`, `CLAUDE.md`.
- Documentazione tecnica in `docs/`: copertura delle API, quirk e comportamenti reali delle API, riferimento alle spec.
- `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `.editorconfig`, `.gitattributes`.
- CI con GitHub Actions: test su PHP 8.3, 8.4 e 8.5 con dipendenze minime e massime, analisi statica e controllo della formattazione.
- Strumenti di sviluppo: PHPStan (livello max), Laravel Pint, script `composer ci`.

### Modificato
- Movimenti: una data di valuta assente, vuota o `"0"` vale `null` (la 2.x usava la data corrente); un movimento senza importo è un errore (la 2.x usava 0).
- Movimenti di fine giornata: il parametro `date` viene mantenuto anche sulle pagine successive.
- SCT: le date `d/m/Y` (esecuzione, valuta, regolamento) sono lette correttamente; nella 2.x i giorni da 1 a 12 erano letti con giorno e mese invertiti.
- Corpo JSON delle richieste generato in modo esatto: gli importi sono scritti dalla stringa decimale, senza passare da un `float`, e non risentono di `serialize_precision`.
- Resubmit SCT Instant: `resubmit` e `resubmitId` vengono sempre inviati insieme (nella 2.x `resubmitId` veniva ignorato senza `setResubmit(true)`).
- **Requisito minimo PHP 8.3.**
- **Guzzle è ora una dipendenza diretta, `^7.15.2 || ^8.0.1`** (minimi scelti per escludere le versioni con advisory di sicurezza). Rimosso `php-http/guzzle7-adapter`, che non veniva usato.
- Parametri nullable resi espliciti: nessuna deprecation su PHP 8.4 e 8.5.
- Test riscritti con PHPUnit 12 e dati deterministici; rimosse le dipendenze di sviluppo Faker e phpdotenv.
- `composer.lock` non è più versionato.
- README riscritto.
- Branch predefinito rinominato da `master` a `main`.
- Line ending normalizzati a LF.

### Rimosso
- **Tutto il codice 2.x**: il client `IntesaSanPaoloClient` 2.x (sostituito dalla nuova facade con lo stesso nome), `Models\*`, `Exceptions\*` e `DataSet\CurrencySet`. La corrispondenza con le API 3.0 è in `UPGRADE-3.0.md`.
- Il percorso senza mutua autenticazione (`disableMutualAuthentication()`): il certificato è obbligatorio.

## [2.1.1] - 2025-11-05

### Modificato
- Rimosso il logo Intesa Sanpaolo dal README.

## [2.1.0] - 2025-03-12

### Modificato
- Anche la richiesta del token OAuth2 usa il certificato di mutua autenticazione.

## [2.0.2] - 2024-05-20

### Corretto
- Saldo: tutti i campi della risposta sono gestiti come nullable, compreso il saldo contabile, e il controllo dei campi assenti è più robusto.

## [2.0.1] - 2023-06-06

### Modificato
- Requisito minimo PHP 8.0.

## [2.0.0] - 2023-06-06

### Aggiunto
- Bonifico SCT ordinario: simulazione, esecuzione, stato, lista e revoca (metodi `*DelayedPayment*`).

### Corretto
- Lettura dell'IBAN di debitore e creditore in entrambe le varianti di nome (`debtorIBAN` e `debtorIban`) e della data di regolamento.

## [1.2.1] - 2022-07-04

### Corretto
- Gestione di IBAN e nomi di debitore e creditore assenti o nulli nelle risposte.

## [1.2.0] - 2022-01-04

### Modificato
- Aggiornamento a Guzzle 7.

## [1.1.2] - 2021-04-08

### Corretto
- In Live `endToEndId` e `siaCode` vengono omessi se vuoti.

## [1.1.1] - 2021-04-07

### Corretto
- Movimenti: gestione di `valueDate` vuota o uguale a `"0"`.

## [1.1.0] - 2021-03-31

### Aggiunto
- Mutua autenticazione con certificato client.
- SCT Instant: simulazione e stato del pagamento.

## [1.0.2] - 2021-02-04

### Aggiunto
- Data di riferimento nella richiesta del saldo.

### Corretto
- Lettura più tollerante dei dati dei pagamenti.

## [1.0.1] - 2020-09-15

### Corretto
- Namespace e cartella delle collection dei model.

## [1.0.0] - 2020-09-04

### Aggiunto
- Prima versione: saldo, movimenti di fine giornata e intraday, esecuzione e lista dei bonifici SCT Instant.

[Unreleased]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/3.0.0...HEAD
[3.0.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.1.1...3.0.0
[2.1.1]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.1.0...2.1.1
[2.1.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.0.2...2.1.0
[2.0.2]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.0.1...2.0.2
[2.0.1]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.0.0...2.0.1
[2.0.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.2.1...2.0.0
[1.2.1]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.2.0...1.2.1
[1.2.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.1.2...1.2.0
[1.1.2]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.0.2...1.1.0
[1.0.2]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.0.1...1.0.2
[1.0.1]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/shellrent/intesasanpaolo-openbanking-php/releases/tag/1.0.0
