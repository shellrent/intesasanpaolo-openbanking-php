# Finding – versione 3.0

Problemi emersi dalla revisione della 2.1.1 (2026-11-12), ciascuno collegato alla task che lo risolve. A fine task aggiorna la colonna **Stato** (`aperto` → `risolto` o `scartato`, con una nota). "Risolto" vuol dire risolto nel codice 3.0: il codice 2.x corrispondente resta invariato finché non viene eliminato.

> Documento temporaneo: viene eliminato al rilascio della 3.0 (T8). I problemi del gestionale che usa la libreria non sono tracciati qui (D-15).

I riferimenti `file:riga` sono relativi al codice 2.1.1.

## Gravità alta

| ID | Problema | Dove (2.x) | Task | Stato |
|---|---|---|---|---|
| F-01 | L'esito VoP non arriva al chiamante: `request()` restituisce solo il body, e gli header `vopMatchResult`, `vopMatchedName` e `vopResultDescription` vanno persi | `IntesaSanPaoloClient.php:219` | T3, T5, T6 | risolto (T5–T6): esito VoP e blocco su SCT Instant e SCT |
| F-02 | Le date `d/m/Y` sono lette male per i giorni 1–12: `new DateTime('02/01/2022')` restituisce il 1° febbraio, e il fallback non scatta | `GenericModel.php:43`, `Collections/PaymentInfos.php:50` | T3, T4–T6 | risolto (T6) |
| F-03 | Resubmit SCT Instant: `resubmit` e `resubmitId` vengono inviati solo se si chiama `setResubmit(true)`; impostare il solo `resubmitId` non ha effetto | `IntesaSanPaoloClient.php:492` | T5 | risolto (T5) |
| F-04 | Viene convertita solo `ClientException` (4xx): gli errori 5xx (500 e 502 sono documentati) e quelli di rete arrivano come eccezioni Guzzle | `IntesaSanPaoloClient.php:215` | T3 | risolto (T3) |
| F-05 | JSON non validato e `success` ignorato: un body non JSON produce un model vuoto senza errori, perché il costruttore accetta `null` | `IntesaSanPaoloClient.php:219`, `GenericModel.php:23` | T3 | risolto (T3) |
| F-06 | Nessun timeout HTTP: il default di Guzzle è infinito | `IntesaSanPaoloClient.php:110` | T3 | risolto (T3) |
| F-07 | La suite di test fallisce: senza `env/.env` fallisce l'avvio (Dotenv), e `PaymentExecutedModelTest` costruisce la risposta senza `payload` | `tests/TestCase.php:57`, `tests/PaymentExecutedModelTest.php:15` | T2 | risolto (T2) |

## Gravità media

| ID | Problema | Dove (2.x) | Task | Stato |
|---|---|---|---|---|
| F-08 | Commissioni SCT: viene letto solo `commissioni`, mentre la spec attuale usa `fees` (Q-15) | `Models/PaymentExecuted.php:61` | T6 | risolto (T6) |
| F-09 | `settlemenDate` è un refuso: `getSettlemenDate()` resta sempre vuoto; il valore corretto è in `getSettlementDate()` | `Models/PaymentExecuted.php:65` | T6 | risolto (T6) |
| F-10 | Gli stati `VRFY` e `ACTC` non sono gestiti né documentati, e i commenti sugli stati sono superati | `Models/PaymentExecuted.php:15`, `Models/PaymentSimulated.php:9` | T5, T6 | risolto (T6) |
| F-11 | Le voci della lista SCT non espongono `customerCRO`, `endToEndId`, `simulationId`, `categoryPurpose` ed `executionDate` | `Models/PaymentInfo.php`, `Models/GenericPaymentInfo.php` | T5, T6 | risolto (T6) |
| F-12 | `Transactions` non espone `numResidualAccountedTransactions`, `pageSize` e `IBAN` | `Models/Collections/Transactions.php` | T4 | risolto (T4) |
| F-13 | Lo stato SCT non supporta i filtri `endToEndId` e `simulationId`, utili per ricostruire l'esito dopo un timeout | `IntesaSanPaoloClient.php:524` | T6 | risolto (T6) |
| F-14 | `getCurrency()` e `getRequestedExecutionDate()` non sono nullable e causano un TypeError se non impostati; la data di esecuzione è opzionale per la spec e l'unica valuta ammessa è EUR | `Models/PaymentExecution.php:81`, `:100` | T6 | risolto (T6) |
| F-15 | `getBalance(DateTime $date)` rende obbligatoria la data, che per la spec è opzionale | `IntesaSanPaoloClient.php:326` | T4 | risolto (T4) |
| F-16 | `categoryPurpose` è fisso a `CASH` | `IntesaSanPaoloClient.php:381`, `:456` | T6 | risolto (T6) |
| F-17 | Le proprietà della risposta sono lette senza controllare che esistano (Warning in PHP 8), e un `createFromFormat()` fallito restituisce `false`, che fa andare in TypeError il getter | `Models/PaymentInfo.php:21`, `GenericPaymentResult.php:61`, `PaymentExecuted.php:51`, `PaymentSimulated.php:23`, `Transaction.php:91-125`, `Collections/Transactions.php:90-111`, `GenericPaymentData.php:110-114` | T3, T4–T6 | risolto (T6) |
| F-18 | Se `valueDate` manca, `Transaction` usa la data corrente: è un dato inventato | `Models/Transaction.php:107` | T4 | risolto (T4) |
| F-19 | Il flag `ForceContentJson` è uno stato condiviso: se il login fallisce, resta attivo per la richiesta successiva | `IntesaSanPaoloClient.php:192` | T3 | risolto (T3) |
| F-20 | Token: scadenza calcolata senza margine; se manca `expires_in` si genera un'eccezione; ogni istanza del client rifà il login | `IntesaSanPaoloClient.php:161` | T3 | risolto (T3) |
| F-21 | I cicli di paginazione non hanno un numero massimo di pagine | `IntesaSanPaoloClient.php:234`, `:597`, `:666` | T3 | risolto (T3) |
| F-22 | L'header `X-ISP-Transaction-ID` (Q-06), necessario per il supporto ISP, non è esposto | `IntesaSanPaoloClient.php:219` | T3 | risolto (T3) |
| F-23 | I saldi di `Transactions` sono dichiarati stringa ma letti come float | `Models/Collections/Transactions.php:49-59`, `:215-234` | T4 | risolto (T4) |
| F-24 | Gli input di pagamento non sono validati: campi obbligatori, importo, lunghezze e caratteri SEPA (Q-39) | `Models/PaymentExecution.php`, `Models/GenericPayment.php` | T5, T6 | risolto (T6) |
| F-36 | La codifica JSON degli importi riporta gli errori di arrotondamento dei float (`0.1 + 0.2` → `0.30000000000000004`) e dipende da `serialize_precision`: gli importi vanno arrotondati a 2 decimali e codificati in modo deterministico | `IntesaSanPaoloClient.php:376`, `:451` | T5, T6 | risolto (T6) |

## Gravità bassa

| ID | Problema | Dove (2.x) | Task | Stato |
|---|---|---|---|---|
| F-25 | Commenti sbagliati e codice inutile: `simulateDelayedPayment` descritto come "Instant", `unset` di `siaCode` che non c'è, `instanceof` superfluo, refuso "retreive" | `IntesaSanPaoloClient.php:364`, `:390`, `:540`, `:725`, `Exceptions/HttpException.php:29` | T3, T5, T6 | risolto (T6) |
| F-26 | `composer.json` richiede `php-http/guzzle7-adapter`, che non serve, ma non Guzzle, che invece è usato direttamente | `composer.json` | T2 | risolto (T2) |
| F-27 | Il README indica PHP ≥ 7.3 (il requisito reale è ^8.0) e rimanda al vecchio portale | `README.md` | T1 | risolto (T1) |
| F-28 | Parametri nullable impliciti, deprecati in PHP 8.4 | varie firme in `src/` | T2, T3–T6 | risolto (T2): nullable resi espliciti nel codice 2.x |
| F-29 | L'HTTP client non si può iniettare, quindi il client non è testabile | `IntesaSanPaoloClient.php:110` | T3 | risolto (T3) |
| F-30 | Il mTLS è opzionale, ma tutte le spec attuali richiedono `/twa/` (O-05) | `IntesaSanPaoloClient.php:118-123` | T3 | risolto (T3) |
| F-31 | Struttura: client monolitico, richieste e risposte nella stessa gerarchia mutabile, stati come stringhe (D-09, D-10) | `src/` | T3–T6 | risolto (T6) |
| F-32 | Gli importi sono float senza arrotondamento a 2 decimali | `Models/GenericPayment.php:173` | T5, T6 | risolto (T6) |
| F-33 | `CurrencySet` è incompleto e superfluo, perché l'SCT è solo in EUR | `DataSet/CurrencySet.php` | T6 | risolto (T6) |
| F-34 | I test usano dati casuali (Faker) invece di fixture deterministiche | `tests/` | T2, T3 | risolto (T6) |
| F-35 | La spec "Verifica esistenza IBAN" è una copia del formal-check (O-06) | spec locali | T7 | risolto (T7): esistenza tramite `chargeabilityMulti` (D-51), da confermare in T8 |
