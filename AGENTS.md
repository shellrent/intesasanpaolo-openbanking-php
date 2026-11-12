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

  Il formatter viene configurato in T2.
- **Date**: nessuna dipendenza da Carbon; in input `DateTimeInterface`, in output `DateTimeImmutable` (D-07).
- **Codice 2.x**: lo stile con tab, proprietà PascalCase e getter è in via di sostituzione; non replicarlo nel codice nuovo.

## Comandi

```bash
composer install
composer test
```

Analisi statica, formatter e CI arrivano con T2: aggiorna questa sezione quando cambiano.

## Mappa del codice (2.x, in sostituzione)

| Percorso | Contenuto |
|---|---|
| `src/IntesaSanPaoloClient.php` | client unico: OAuth2, richieste HTTP, paginazione, tutti gli endpoint |
| `src/Models/` | model di richiesta e di risposta (gerarchia `GenericModel` → `GenericPayment*`) |
| `src/Models/Collections/` | `Transactions`, `PaymentInfos` |
| `src/Exceptions/` | `Exception`, `HttpException` |
| `src/DataSet/CurrencySet.php` | costanti delle valute |
| `tests/` | test dei model (PHPUnit 9) |

La struttura di destinazione della 3.0 è descritta in `docs/v3/DECISIONS.md` (D-10) e, da T3, in `docs/architecture.md`.

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
