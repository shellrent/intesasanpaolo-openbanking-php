# Contribuire

Grazie per l'interesse. Prima di iniziare leggi [AGENTS.md](AGENTS.md): contiene le regole del progetto, valide sia per le persone sia per gli agent AI.

## Flusso di lavoro

1. Apri una issue per discutere modifiche importanti o nuove API prima di scrivere codice.
2. Crea un branch dal branch di destinazione: `main` per la 2.x; `3.x` per la 3.0 finché la riscrittura è in corso.
3. Apri una pull request verso lo stesso branch. Le PR verso `main` richiedono un'approvazione.

## Requisiti di una pull request

- `composer ci` passa: validazione di `composer.json`, formattazione (`composer format` la corregge), analisi statica e test. La CI ripete i test su PHP 8.3, 8.4 e 8.5, con dipendenze minime e massime.
- Il codice nuovo ha i suoi test. Per le API si usano fixture deterministiche, senza chiamate reali.
- La documentazione in `docs/` e il `CHANGELOG.md` (sezione *Unreleased*) sono aggiornati.
- Un comportamento delle API diverso dalla spec è documentato in [docs/api-quirks.md](docs/api-quirks.md), con la sua fonte.

## Regole

- **Mai** committare le spec ISP scaricate dall'area riservata SmartHub ([docs/specs-reference.md](docs/specs-reference.md)).
- **Mai** dati reali: credenziali, certificati, IBAN, CRO, nominativi, risposte di produzione non anonimizzate.
- Documentazione in italiano; codice, PHPDoc e messaggi di commit in inglese, secondo [Conventional Commits](https://www.conventionalcommits.org/it/v1.0.0/).
