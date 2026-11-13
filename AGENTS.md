# AGENTS.md

Istruzioni per agent AI e sviluppatori che lavorano su questo repository. Leggile per intero prima di modificare il codice.

## Il progetto

SDK PHP **non ufficiale** per le API SmartHub / Open Banking di Intesa Sanpaolo (ISP): saldo, movimenti, bonifici SCT e SCT Instant, verifiche IBAN.

- È il pacchetto pubblico `shellrent/intesasanpaolo-openbanking-php` (Packagist, licenza MIT): **tutto ciò che entra nel repo è pubblico**.
- Il consumer principale è il gestionale interno di Shellrent (repository privato), che lo usa in produzione per eseguire bonifici reali. Ogni cambiamento di comportamento può spostare denaro.
- Documentazione ufficiale ISP: https://smarthub.intesasanpaolo.com/it/homepage/documenti-sviluppo.html

## Versioni

La versione corrente è la 3.0, una riscrittura completa della 2.x. **Prima di cambiare un comportamento o la struttura leggi [`docs/decisions.md`](docs/decisions.md)**: riporta le decisioni di progettazione (D-01 e seguenti), con le motivazioni, e le task della riscrittura (T0–T9) che le voci citano.

## Regole fondamentali

1. **Non committare mai le spec ISP**, cioè i JSON Swagger scaricati dall'area riservata SmartHub, né copie estese del loro contenuto. Sono un riferimento solo locale: vedi [docs/specs-reference.md](docs/specs-reference.md).
2. **Nessun dato reale** in codice, test, fixture, commit o messaggi: niente IBAN reali, CRO, nominativi, importi, client ID e secret, certificati, risposte di produzione non anonimizzate. Usa dati fittizi o gli esempi della sandbox.
3. **I quirk sono intenzionali.** Molti comportamenti che sembrano sbagliati rispetto alla spec derivano dall'uso reale delle API e sono documentati in [docs/api-quirks.md](docs/api-quirks.md). Non "normalizzarli" alla spec. Per cambiare un comportamento serve un'evidenza (una risposta reale, la spec, una FAQ), da annotare in `api-quirks.md` insieme alla fonte.
4. **Quando spec e realtà divergono, leggi entrambe le varianti** (per esempio `fees` e `commissioni`) invece di sceglierne una.
5. **Mai `new DateTime($stringaApi)`**: PHP interpreta `02/01/2022` come 1° febbraio. Ogni campo data va letto con il suo formato esplicito (tabella Q-10 in `api-quirks.md`).
6. **Mai chiamare l'ambiente Live** da test, script o sessioni di sviluppo. Unica eccezione: la suite di integrazione di sola lettura (`LiveReadOnlyTest`), solo su richiesta esplicita del maintainer, con credenziali locali e il blocco delle scritture attivo ([docs/testing.md](docs/testing.md)). Simulazioni ed esecuzioni si provano solo in sandbox.
7. **Pagamenti**: ogni modifica a simulazione, esecuzione, resubmit o revoca richiede test dedicati e una voce nel CHANGELOG.

## Convenzioni

- **Lingua**: documentazione in italiano; codice, identificatori, PHPDoc, messaggi di eccezione e messaggi di commit in inglese.
- **PHP 8.3+**, compatibile con 8.4 e 8.5:
  - niente funzionalità introdotte solo in 8.4+ (property hooks, visibilità asimmetrica);
  - niente costrutti deprecati (nullable impliciti, cast non canonici come `(integer)`, `null` come indice di array).
- **Stile moderno, Laravel-style** (D-04):
  - PER Coding Style, 4 spazi, camelCase, `declare(strict_types=1);`;
  - classi `final` se non sono pensate per l'estensione;
  - DTO `readonly` con proprietà pubbliche invece dei getter.

  La formattazione la applica Laravel Pint (`pint.json`), che aggiunge anche `declare_strict_types`, `strict_comparison` e `strict_param`.
- **Date**: nessuna dipendenza da Carbon; in input `DateTimeInterface`, in output `DateTimeImmutable` (D-07).
- **Importi**: mai `float`. Sono stringhe decimali in ingresso e in uscita, si leggono con `Payload::amount()` e si scrivono con `JsonNumber` (D-65).
- **Codice 2.x**: è stato eliminato in T6; per confrontare un comportamento con la 2.x si guarda il tag `2.1.1`.

## Comandi

```bash
composer install
composer test              # PHPUnit 12, test unitari
composer test:integration  # API reali, opzionale (docs/testing.md)
composer analyse           # PHPStan, livello max
composer format            # Laravel Pint (preset laravel)
composer format:check      # verifica la formattazione senza modificare i file
composer ci                # tutti i controlli della CI: validate, format:check, analyse, test
```

La CI (GitHub Actions, `.github/workflows/ci.yml`) esegue i test su PHP 8.3, 8.4 e 8.5, ciascuno con dipendenze minime e massime. L'analisi statica e la formattazione girano su PHP 8.3 con le dipendenze massime. `composer.lock` non è versionato.

### Ambiente locale: estensione PECL `psr`

Se il PHP locale carica l'estensione PECL `psr`, spesso rimasta installata dagli ambienti Phalcon 4 (verificalo con `php -m | grep psr`), ci sono due conseguenze. Phalcon 5 non la richiede (D-20):
- **Guzzle 8 non funziona**: psr7 3.x è incompatibile con le interfacce definite dall'estensione e il caricamento di `GuzzleHttp\Psr7\Request` va in errore fatale. Con Guzzle 7 non ci sono problemi.
- **PHPStan legge male i tipi PSR-7**: tutti i metodi di `Psr\Http\Message\*` risultano `mixed`.

In questo caso esegui i controlli con un PHP senza l'estensione, come avviene in CI. Gli script composer usano il PHP con cui viene lanciato Composer, quindi per esempio:

```bash
php8.4 $(command -v composer) ci
```

## Mappa del codice

Codice 3.0 (dettagli in [docs/architecture.md](docs/architecture.md)):

| Percorso | Contenuto |
|---|---|
| `src/IntesaSanPaoloClient.php` | facade: `accounts()`, poi i pagamenti e gli strumenti IBAN (pubblico) |
| `src/Api/` | un oggetto per area: `AccountsApi`, `SctInstantApi`, `SctApi`, `IbanToolsApi` (pubblici); `PaymentGuard` (interno) |
| `src/Request/` | richieste di pagamento validate: `InstantPayment`, `SctPayment` (pubbliche); `Validator` (interno) |
| `src/Response/` | DTO readonly di risposta, per area (pubblici) |
| `src/Enum/` | stati come enum tolleranti con `fromApi()` (pubblici) |
| `src/Config/` | `ClientConfig`, `Environment`, `MutualTlsCertificate` (pubblico) |
| `src/Auth/` | `AccessToken`, `TokenStore`, `InMemoryTokenStore` (pubblici); `TokenProvider` (interno) |
| `src/Http/` | `Sender`, `Transport`, `ApiResponse`, `Paginator`, `SandboxLinkRewriter` (interni) |
| `src/Support/` | `DateParser`, `DateFormat`, `Payload`, `Decimal`, `Json`, `JsonNumber`, `Url`, `Clock` (interni); `Amount`, `SepaText` (pubblici) |
| `src/Exception/` | eccezioni con l'interfaccia comune `OpenBankingException` (pubbliche) |
| `tests/Support/` | `MockHttp`, `FrozenClock` |
| `tests/Unit/` | test del codice 3.0, con la base `TestCase` |
| `tests/Fixtures/` | risposte JSON fittizie per area, lette con `MockHttp::fixture()` |
| `tests/Integration/` | test opzionali contro le API reali: sandbox e Live in sola lettura |

Le classi interne sono marcate `@internal`: non fanno parte dell'API pubblica e possono cambiare in qualsiasi versione.

## Spec OpenAPI locali

Le spec stanno fuori dal repo, di norma in `../openbanking-openapi`: spec Live nella radice, poi `sandbox/` e `old/`. Se ti serve un percorso interno al repo puoi creare un link simbolico `specs`, già ignorato da git:

```bash
ln -s ../openbanking-openapi specs
```

[docs/specs-reference.md](docs/specs-reference.md) indica quali file usare, quali sono doppioni e quali anomalie hanno. Se le spec non sono disponibili, lavora da `docs/api-coverage.md` e `docs/api-quirks.md` e dichiaralo.

## Documentazione

L'indice completo è in [docs/README.md](docs/README.md).

## Come lavorare

1. Una modifica alla volta, e solo dopo l'approvazione del maintainer.
2. Lavora su un branch dedicato e apri una PR verso `main`, che richiede un'approvazione.
3. Una modifica è completa quando:
   - `composer ci` è verde; per le API toccate, se il maintainer lo chiede, anche `composer test:integration`;
   - documentazione e `CHANGELOG.md` (sezione Unreleased) sono aggiornati;
   - i nuovi comportamenti delle API sono in `docs/api-quirks.md`, con la fonte;
   - le nuove decisioni sono in `docs/decisions.md`, con il primo identificativo libero.
4. Commit in inglese secondo Conventional Commits (es. `feat(sct): add revoke endpoint`). Tag e rilasci li decide il maintainer.
