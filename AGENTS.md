# AGENTS.md

Istruzioni per agent AI e sviluppatori che lavorano su questo repository. Leggile per intero prima di modificare il codice.

## Il progetto

SDK PHP **non ufficiale** per le API SmartHub / Open Banking di Intesa Sanpaolo (ISP): saldo, movimenti, bonifici SCT e SCT Instant, verifiche IBAN.

- È il pacchetto pubblico `shellrent/intesasanpaolo-openbanking-php` (Packagist, licenza MIT): **tutto ciò che entra nel repo è pubblico**.
- Il consumer principale è il gestionale interno di Shellrent (repository privato), che lo usa in produzione per eseguire bonifici reali. Ogni cambiamento di comportamento può spostare denaro.
- Documentazione ufficiale ISP: https://smarthub.intesasanpaolo.com/it/homepage/documenti-sviluppo.html

## Stato attuale: riscrittura 3.0 in corso

Sul branch `3.x` è in corso la riscrittura per la versione 3.0.0. **Prima di iniziare qualsiasi lavoro leggi [`docs/v3/ROADMAP.md`](docs/v3/ROADMAP.md)**, che riporta le task, il loro stato e il registro di avanzamento. Le motivazioni delle scelte sono in [`docs/v3/DECISIONS.md`](docs/v3/DECISIONS.md), i problemi da risolvere in [`docs/v3/FINDINGS.md`](docs/v3/FINDINGS.md).

`docs/v3/` è temporanea e viene eliminata al rilascio della 3.0 (task T8).

## Regole fondamentali

1. **Non committare mai le spec ISP**, cioè i JSON Swagger scaricati dall'area riservata SmartHub, né copie estese del loro contenuto. Sono un riferimento solo locale: vedi [docs/specs-reference.md](docs/specs-reference.md).
2. **Nessun dato reale** in codice, test, fixture, commit o messaggi: niente IBAN reali, CRO, nominativi, importi, client ID e secret, certificati, risposte di produzione non anonimizzate. Usa dati fittizi o gli esempi della sandbox.
3. **I quirk sono intenzionali.** Molti comportamenti che sembrano sbagliati rispetto alla spec derivano dall'uso reale delle API e sono documentati in [docs/api-quirks.md](docs/api-quirks.md). Non "normalizzarli" alla spec. Per cambiare un comportamento serve un'evidenza (una risposta reale, la spec, una FAQ), da annotare in `api-quirks.md` insieme alla fonte.
4. **Quando spec e realtà divergono, leggi entrambe le varianti** (per esempio `fees` e `commissioni`) invece di sceglierne una.
5. **Mai `new DateTime($stringaApi)`**: PHP interpreta `02/01/2022` come 1° febbraio. Ogni campo data va letto con il suo formato esplicito (tabella Q-10 in `api-quirks.md`).
6. **Mai chiamare l'ambiente Live** da test, script o sessioni di sviluppo. I test di integrazione usano solo la sandbox e si attivano esplicitamente.
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
- **Codice 2.x**: lo stile con tab, proprietà PascalCase e getter è in via di sostituzione; non replicarlo nel codice nuovo.

## Comandi

```bash
composer install
composer test           # PHPUnit 12
composer analyse        # PHPStan, livello max
composer format         # Laravel Pint (preset laravel)
composer format:check   # verifica la formattazione senza modificare i file
composer ci             # tutti i controlli della CI: validate, format:check, analyse, test
```

La CI (GitHub Actions, `.github/workflows/ci.yml`) esegue i test su PHP 8.3, 8.4 e 8.5, ciascuno con dipendenze minime e massime. L'analisi statica e la formattazione girano su PHP 8.3 con le dipendenze massime. `composer.lock` non è versionato.

### Codice 2.x escluso dagli strumenti

I file 2.x (`src/Legacy`, `src/DataSet`, `src/Exceptions`, `src/Models`) sono esclusi dall'analisi di PHPStan e dalla formattazione di Pint finché non vengono riscritti. Quando una task sostituisce uno di questi file, rimuovilo dalle esclusioni in `phpstan.neon.dist` e `pint.json`. I test della 2.x stanno in `tests/Unit/Legacy/` e si eliminano insieme alla classe che coprono.

### Ambiente locale: estensione PECL `psr`

Se il PHP locale carica l'estensione PECL `psr`, spesso installata insieme a Phalcon (verificalo con `php -m | grep psr`), ci sono due conseguenze:
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
| `src/Api/` | un oggetto per area: `AccountsApi`, `SctInstantApi` (pubblici) |
| `src/Request/` | richieste di pagamento validate, es. `InstantPayment` (pubbliche); `Validator` (interno) |
| `src/Response/` | DTO readonly di risposta, per area (pubblici) |
| `src/Enum/` | stati come enum tolleranti con `fromApi()` (pubblici) |
| `src/Config/` | `ClientConfig`, `Environment`, `MutualTlsCertificate` (pubblico) |
| `src/Auth/` | `AccessToken`, `TokenStore`, `InMemoryTokenStore` (pubblici); `TokenProvider` (interno) |
| `src/Http/` | `Sender`, `Transport`, `ApiResponse`, `Paginator`, `SandboxLinkRewriter` (interni) |
| `src/Support/` | `DateParser`, `DateFormat`, `Payload`, `Json`, `Url`, `Clock` (interni); `SepaText` (pubblico) |
| `src/Exception/` | eccezioni con l'interfaccia comune `OpenBankingException` (pubbliche) |
| `tests/Support/` | `MockHttp`, `FrozenClock` |
| `tests/Unit/` | test del codice 3.0, con la base `TestCase` |
| `tests/Fixtures/` | risposte JSON fittizie per area, lette con `MockHttp::fixture()` |

Le classi interne sono marcate `@internal`: non fanno parte dell'API pubblica e possono cambiare in qualsiasi versione.

Codice 2.x, in sostituzione fino a T6:

| Percorso | Contenuto |
|---|---|
| `src/Legacy/IntesaSanPaoloClient.php` | client 2.x, `@deprecated`, ormai solo per l'SCT ordinario |
| `src/Models/`, `src/Models/Collections/` | model 2.x dell'SCT ordinario, `PaymentInfos` |
| `src/Exceptions/` (plurale) | `Exception`, `HttpException` |
| `src/DataSet/CurrencySet.php` | costanti delle valute |
| `tests/Unit/Legacy/` | test del codice 2.x, compresi i quirk di trasporto verificati con il `MockHandler` di Guzzle |

## Spec OpenAPI locali

Le spec stanno fuori dal repo, di norma in `../openbanking-openapi`: spec Live nella radice, poi `sandbox/` e `old/`. Se ti serve un percorso interno al repo puoi creare un link simbolico `specs`, già ignorato da git:

```bash
ln -s ../openbanking-openapi specs
```

[docs/specs-reference.md](docs/specs-reference.md) indica quali file usare, quali sono doppioni e quali anomalie hanno. Se le spec non sono disponibili, lavora da `docs/api-coverage.md` e `docs/api-quirks.md` e dichiaralo.

## Documentazione

L'indice completo è in [docs/README.md](docs/README.md).

## Come lavorare su una task della 3.0

1. Leggi `docs/v3/ROADMAP.md` e i finding collegati alla task in `docs/v3/FINDINGS.md`.
2. Lavora su una sola task alla volta, sul branch `3.x`, e solo dopo l'approvazione del maintainer.
3. A fine task rispetta la "definizione di completata" della roadmap:
   - test verdi;
   - documentazione e `CHANGELOG.md` aggiornati;
   - stato dei finding aggiornato;
   - nuove decisioni registrate;
   - voce nel registro di avanzamento.
4. Commit in inglese secondo Conventional Commits (es. `feat(sct): add revoke endpoint`), poi push di `3.x`. Nessun tag fino al rilascio.
