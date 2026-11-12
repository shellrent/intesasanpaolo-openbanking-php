# Copertura delle API

Stato di implementazione delle API SmartHub nella libreria. I file di spec di riferimento per ogni API sono in [specs-reference.md](specs-reference.md), i comportamenti particolari in [api-quirks.md](api-quirks.md).

I nomi dei metodi della 3.0 vengono definiti nelle task da T3 a T7: la colonna "3.0" indica la task che implementa l'API.

## Conti

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Saldo | `GET /accounts/{IBAN}/balance` | `getBalance()` | T4 | data opzionale (default: oggi) |
| Movimenti di fine giornata | `GET /accounts/{IBAN}/transactions` | `getTransactions()` | T4 | paginazione `nextPage`, storico fino a 18 mesi |
| Movimenti intraday | `GET /accounts/{IBAN}/transactions/today` | `getTodayTransactions()` | T4 | include i movimenti non contabilizzati |

## SCT Instant (bonifico istantaneo)

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Simulazione | `POST /payments/sct/instant/simulation` | `simulateInstantPayment()` | T5 | esito VoP negli header |
| Esecuzione | `POST /payments/sct/instant` | `createInstantPayment()` | T5 | richiede `simulationId` (5 min), resubmit per `PNDG` |
| Stato | `GET /payments/sct/instant/{IBAN}/history/{orderId}` | `getPaymentStatus()` | T5 | |
| Lista | `GET /payments/sct/instant/{IBAN}/history` | `getPaymentsList()` | T5 | paginazione `links.next` |
| Raggiungibilità IBAN | `GET /payments/sct/instant/reachability` | — | T7 | |
| Simulazione ISO 20022 | `POST /payments/sct/instant/simulation/iso` | — | non pianificata | body XML pain.001 |
| Esecuzione ISO 20022 | `POST /payments/sct/instant/iso` | — | non pianificata | body XML pain.001 |

## SCT (bonifico ordinario)

Nella 2.x questi metodi usano il termine "Delayed".

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Simulazione | `POST /payments/bonsct/simulation` | `simulateDelayedPayment()` | T6 | esito VoP negli header |
| Esecuzione | `POST /payments/bonsct` | `createDelayedPayment()` | T6 | richiede `simulationId` (5 min) |
| Stato | `GET /payments/bonsct/{iban}/history` | `getDelayedPaymentStatus()` | T6 | filtri: `customerCRO`, `paymentId`, `endToEndId`, `simulationId` (la 2.x supporta solo i primi due) |
| Lista | `GET /payments/bonsct/{iban}/list` | `getDelayedPaymentsList()` | T6 | `offset` = identificativo di un pagamento |
| Revoca | `GET /payments/bonsct/{iban}/revoke` | `revokeDelayedPayment()` | T6 | solo entro i cut-off |
| Simulazione ISO 20022 | `POST /payments/bonsct/simulation/iso` | — | non pianificata | body XML pain.001 |
| Esecuzione ISO 20022 | `POST /payments/bonsct/iso` | — | non pianificata | body XML pain.001 |

## Strumenti IBAN

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Verifica sintattica | `GET /tools/iban/formal-check` | — | T7 | campi "numerici" da trattare come stringhe |
| Esistenza e congruenza con CF/P.IVA | `GET /tools/iban/chargeabilityMulti` | — | T7 | anche banche aderenti a CBI Globe |
| Verifica esistenza | da chiarire | — | T7 | la spec scaricata è una copia del formal-check |

## Altre API SmartHub

| API | Stato | Note |
|---|---|---|
| Localizzazione ATM e filiali (`/locator/atm`, `/locator/branch`) | non pianificata | spec disponibili solo in sandbox |
| Servizi di incasso MAV | non pianificata | documentata su SmartHub, spec non scaricata |
