# Changelog

Le modifiche rilevanti del progetto sono documentate in questo file.

Il formato segue [Keep a Changelog](https://keepachangelog.com/it-IT/1.1.0/) e il progetto adotta il [Versionamento Semantico](https://semver.org/lang/it/). Le voci fino alla 2.1.1 sono state ricostruite dalla cronologia git.

## [Unreleased]

Versione 3.0.0 in sviluppo sul branch `3.x`. Contiene modifiche incompatibili con la 2.x, che verranno documentate in `UPGRADE-3.0.md`.

### Aggiunto
- Documentazione per agent AI e sviluppatori: `AGENTS.md`, `CLAUDE.md`.
- Documentazione tecnica in `docs/`: copertura delle API, quirk e comportamenti reali delle API, riferimento alle spec.
- `CHANGELOG.md`, `SECURITY.md`, `CONTRIBUTING.md`, `.editorconfig`, `.gitattributes`.
- CI con GitHub Actions: test su PHP 8.3, 8.4 e 8.5 con dipendenze minime e massime, analisi statica e controllo della formattazione.
- Strumenti di sviluppo: PHPStan (livello max), Laravel Pint, script `composer ci`.

### Modificato
- **Requisito minimo PHP 8.3.**
- **Guzzle è ora una dipendenza diretta, `^7.15.2 || ^8.0.1`** (minimi scelti per escludere le versioni con advisory di sicurezza). Rimosso `php-http/guzzle7-adapter`, che non veniva usato.
- Parametri nullable resi espliciti: nessuna deprecation su PHP 8.4 e 8.5.
- Test riscritti con PHPUnit 12 e dati deterministici; rimosse le dipendenze di sviluppo Faker e phpdotenv.
- `composer.lock` non è più versionato.
- README riscritto.
- Branch predefinito rinominato da `master` a `main`.
- Line ending normalizzati a LF.

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

[Unreleased]: https://github.com/shellrent/intesasanpaolo-openbanking-php/compare/2.1.1...3.x
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
