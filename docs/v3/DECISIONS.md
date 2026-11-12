# Decisioni – versione 3.0

Registro delle decisioni prese durante la riscrittura. Ogni voce indica data, decisione, motivazione ed eventuali alternative scartate.

> Documento temporaneo: viene eliminato al rilascio della 3.0 (T8). Le decisioni ancora rilevanti confluiscono in `docs/architecture.md`, `UPGRADE-3.0.md` e `CHANGELOG.md`.

## Decise

### D-01 · Major 3.0.0 su branch dedicato — 2026-11-12
Tutte le correzioni e i miglioramenti confluiscono in una nuova major 3.0.0, sviluppata sul branch `3.x`. Su Packagist il branch diventa `3.x-dev`, così il gestionale può provare la 3.0 prima del rilascio.
- Push di `3.x` al termine di ogni task. Nessun tag fino al rilascio.

### D-02 · Branch predefinito `main` — 2026-11-12
`master` rinominato in `main` su GitHub; la protezione (PR con 1 approvazione) è passata a `main`. Eliminato il branch `php80`, già interamente incluso.

### D-03 · Lingua
La documentazione è in **italiano**; codice, identificatori, PHPDoc, messaggi di eccezione e messaggi di commit in **inglese**.

### D-04 · Stile di codice moderno (Laravel-style)
Si abbandona lo stile della 2.x (tab, `if( ... )`, proprietà PascalCase, getter). Il codice nuovo segue:
- PER Coding Style, indentazione di 4 spazi, camelCase;
- `declare(strict_types=1);` in ogni file;
- classi `final` se non sono pensate per l'estensione;
- DTO `readonly` con **proprietà pubbliche** al posto dei getter, più metodi di comodo dove servono (es. `$vop->isMatch()`).

Lo strumento di formattazione (Laravel Pint con preset `laravel`, oppure PHP-CS-Fixer con `@PER-CS`) si sceglie in T2 (O-02).

### D-05 · PHP 8.3+, compatibile con 8.4 e 8.5
- Requisito `php: ^8.3`.
- Sintassi ammessa fino a PHP 8.3: classi readonly, enum, costanti tipizzate, `#[\Override]`. Nessuna funzionalità introdotta solo in 8.4+ (property hooks, visibilità asimmetrica).
- Niente costrutti deprecati in 8.4 o 8.5: nullable impliciti, cast non canonici come `(integer)`/`(boolean)`, `null` come indice di array.
- CI su 8.3, 8.4 e 8.5, con le deprecation trattate come errori.
- PHPUnit 12.5: è l'ultima versione che supporta 8.3 (la 13 richiede 8.4).

### D-06 · Guzzle `^7.9 || ^8.0`
Guzzle diventa una dipendenza diretta e si rimuove `php-http/guzzle7-adapter`, che non viene usato.
- Il vincolo include la 7 perché il gestionale è su Guzzle 7.
- Il client Guzzle si può iniettare, così i test possono usare un `MockHandler`.
- La compatibilità con Guzzle 8 si verifica in T2.

### D-07 · Date senza Carbon
- Input: `DateTimeInterface` (quindi anche Carbon).
- Output: `DateTimeImmutable`.
- Ogni campo si legge con il suo formato esplicito ([api-quirks.md](../api-quirks.md), Q-10).
- Le date senza orario si interpretano a mezzanotte in `Europe/Rome`.

Motivazione: richiedere Carbon imporrebbe una versione a chi usa la libreria (il gestionale è su Carbon 2, l'attuale è la 3) e non risolverebbe il problema, perché `Carbon::parse()` sbaglia giorno e mese esattamente come `DateTime`. Chi usa Carbon converte con `CarbonImmutable::instance()` o `new Carbon($date)`; la seconda strada è verificata con Carbon 2.73.

### D-08 · Spec ISP e dati reali fuori dal repo
Le spec scaricate dall'area riservata restano un riferimento locale ([specs-reference.md](../specs-reference.md)). Nel repo non entrano mai dati reali: IBAN, CRO, nominativi, credenziali, certificati o risposte di produzione non anonimizzate.

### D-09 · Ristrutturazione insieme ai fix
I bugfix si fanno riscrivendo un'area alla volta nella nuova struttura (T3–T6), non prima sul codice 2.x: altrimenti molte correzioni andrebbero fatte due volte. Le incompatibilità con la 2.x si documentano in `UPGRADE-3.0.md`.

### D-10 · Struttura di destinazione
Una facade con sotto-API per area: `accounts()`, `sctInstant()`, `sct()`, `ibanTools()`. Sotto di essa:

```
src/
  IntesaSanPaoloClient.php   facade
  Config/                    ClientConfig (readonly), Environment (enum), MutualTlsCertificate
  Http/                      Transport, TokenProvider, Paginator, ApiResponse
  Api/                       AccountsApi, SctInstantApi, SctApi, IbanToolsApi
  Request/                   richieste immutabili e validate
  Response/                  DTO readonly costruiti con fromPayload()
  Enum/                      stati, esiti di validazione, esito VoP
  Support/                   DateParser, Payload (lettura tipizzata e tollerante)
  Exception/                 gerarchia di eccezioni con un'interfaccia comune
```

Nomi: il termine "Delayed" della 2.x diventa "Sct", che è la terminologia ISP per il bonifico ordinario; "Instant" diventa "SctInstant". I dettagli si definiscono in T3 e si documentano in `docs/architecture.md`.

### D-11 · Stati tolleranti
Gli stati sono enum, ma il valore originale (`raw`) è sempre conservato.
- L'enum si ricava dal primo token del valore, quindi `ACSC acquired` diventa `ACSC`.
- Si accettano gli alias noti (`PDNG` per `PNDG`, `Cancelled` per `Canceled`).
- Un valore sconosciuto non lancia eccezioni: l'enum vale `null` e resta disponibile il `raw`.

Motivazione: le API restituiscono varianti non documentate ([api-quirks.md](../api-quirks.md), Q-25 e seguenti).

### D-12 · Fuori ambito per la 3.0
Restano fuori: endpoint ISO 20022 (pain.001), localizzazione ATM e filiali, incassi MAV. Si possono riconsiderare in seguito.

### D-13 · Nessun hotfix sulla 2.x
Un rilascio 2.2.0 con le sole correzioni urgenti (VoP, resubmit, date) è stato proposto ma non richiesto: non è pianificato.

### D-14 · Area di lavoro temporanea
`docs/v3/` (roadmap, finding, decisioni) è temporanea e si elimina in T8. Ciò che resta valido si sposta prima in `CHANGELOG.md`, `UPGRADE-3.0.md` o nella documentazione permanente.

### D-15 · Finding lato consumer fuori da questo repo
Il repo è pubblico: i problemi trovati nel gestionale interno (task T9) non si tracciano qui, ma nel repository del gestionale.

## Aperte

| ID | Questione | Da decidere in |
|---|---|---|
| O-01 | Togliere `composer.lock` dal repo (prassi comune per le librerie; la CI prova dipendenze minime e massime) | T2 |
| O-02 | Formatter: Laravel Pint (preset `laravel`) o PHP-CS-Fixer (`@PER-CS`) | T2 |
| O-03 | Cache del token tra istanze: interfaccia propria minimale o PSR-16 | T3 |
| O-04 | Logging opzionale con PSR-3 (metadati delle chiamate, mai segreti) | T3 |
| O-05 | Rendere il mTLS obbligatorio, visto che tutte le spec attuali usano `/twa/` | T3 |
| O-06 | Quale endpoint corrisponde a "Verifica esistenza IBAN" | T7 |
