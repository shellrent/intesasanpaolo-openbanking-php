# Date e formati

Le API Intesa Sanpaolo usano formati di data diversi a seconda dell'API e del campo: la tabella completa è in [api-quirks.md](api-quirks.md), Q-10. Questo documento descrive come l'SDK legge e scrive le date.

## Regole

1. **Mai `new DateTime($valoreApi)`, e nemmeno `Carbon::parse()`.** Entrambi leggono `02/01/2022` come 1° febbraio (formato americano) senza segnalare errori, mentre con `13/01/2022` lanciano un'eccezione (Q-09).
2. **Ogni campo si legge con il suo formato esplicito**, tramite `Payload::date(DateFormat::…, 'campo')` o `Payload::dateTime('campo')`.
3. **Valori non validi**: una data impossibile (`31/02/2022`), con dati in più (`02/01/2022 10:00`) o nel formato sbagliato genera un errore, invece di essere "aggiustata".
4. **Assenza di valore**: per una data, un campo mancante, vuoto o uguale a `"0"` vale `null` (Q-23).

## Formati

| `DateFormat` | Formato | Esempio | Dove si usa |
|---|---|---|---|
| `Compact` | `Ymd` | `20180131` | saldo, movimenti, filtri delle liste |
| `Italian` | `d/m/Y` | `31/01/2018` | date dei bonifici SCT, valuta nelle liste dei pagamenti |
| — (`DateParser::dateTime`) | ISO 8601 | `2018-05-04T09:52:10.289+0200`, `2017-12-31T23:59:60Z` | `executionDate` di saldo e movimenti, `date` dei pagamenti SCT Instant |

## Valori restituiti

- Tutte le date restituite sono `DateTimeImmutable`.
- **Date senza orario** (`Compact`, `Italian`): mezzanotte nel fuso `Europe/Rome`, perché sono date contabili italiane.
- **Date-ora ISO 8601**: mantengono l'offset della risposta. Senza offset si interpretano in `Europe/Rome`. Il secondo intercalare `23:59:60` degli esempi diventa il minuto successivo.

## Valori in ingresso

- I metodi dell'SDK accettano `DateTimeInterface`, quindi anche Carbon e le sue sottoclassi.
- Per i parametri di sola data si usa **la data di calendario dell'oggetto così com'è, senza conversioni di fuso**: un `DateTime` del 31/01 alle 23:30 UTC diventa `20260131`, anche se a Roma è già il 1° febbraio.

## Carbon

L'SDK non dipende da Carbon (D-07), ma lo si usa senza conversioni in ingresso e con una riga in uscita:

```php
use Carbon\CarbonImmutable;

$date = CarbonImmutable::instance($balance->inquiryDate);   // DateTimeImmutable → CarbonImmutable
$timing = new Timing($transaction->valueDate);              // le sottoclassi di Carbon accettano DateTimeInterface
```

## Codice

| Classe | Ruolo |
|---|---|
| `Support\DateFormat` | enum dei formati di sola data |
| `Support\DateParser` | parsing (`date()`, `dateTime()`) e formattazione (`format()`); `@internal` |
| `Support\Payload` | `date()` e `dateTime()` sui campi della risposta, con errori che indicano il campo |
