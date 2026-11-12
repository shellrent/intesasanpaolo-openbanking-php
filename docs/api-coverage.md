# Copertura delle API

Stato di implementazione delle API SmartHub nella libreria. I file di spec di riferimento per ogni API sono in [specs-reference.md](specs-reference.md), i comportamenti particolari in [api-quirks.md](api-quirks.md).

La colonna "3.0" indica il metodo della 3.0 (✅ disponibile), oppure che l'API non è pianificata.

## Conti

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Saldo | `GET /accounts/{IBAN}/balance` | `getBalance()` | ✅ `accounts()->balance()` | data opzionale (default: oggi) |
| Movimenti di fine giornata | `GET /accounts/{IBAN}/transactions` | `getTransactions()` | ✅ `accounts()->transactions()` | paginazione `nextPage`, storico fino a 18 mesi |
| Movimenti intraday | `GET /accounts/{IBAN}/transactions/today` | `getTodayTransactions()` | ✅ `accounts()->todayTransactions()` | include i movimenti non contabilizzati |

## SCT Instant (bonifico istantaneo)

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Simulazione | `POST /payments/sct/instant/simulation` | `simulateInstantPayment()` | ✅ `sctInstant()->simulate()` | esito VoP negli header |
| Esecuzione | `POST /payments/sct/instant` | `createInstantPayment()` | ✅ `sctInstant()->execute()`, `resubmit()` | richiede la simulazione (5 min), resubmit per `PNDG` |
| Stato | `GET /payments/sct/instant/{IBAN}/history/{orderId}` | `getPaymentStatus()` | ✅ `sctInstant()->status()` | |
| Lista | `GET /payments/sct/instant/{IBAN}/history` | `getPaymentsList()` | ✅ `sctInstant()->payments()` | paginazione `links.next` |
| Raggiungibilità IBAN | `GET /payments/sct/instant/reachability` | — | ✅ `sctInstant()->reachability()` | se non raggiungibile, si usa l'SCT ordinario |
| Simulazione ISO 20022 | `POST /payments/sct/instant/simulation/iso` | — | non pianificata | body XML pain.001 |
| Esecuzione ISO 20022 | `POST /payments/sct/instant/iso` | — | non pianificata | body XML pain.001 |

## SCT (bonifico ordinario)

Nella 2.x questi metodi usano il termine "Delayed".

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Simulazione | `POST /payments/bonsct/simulation` | `simulateDelayedPayment()` | ✅ `sct()->simulate()` | esito VoP negli header |
| Esecuzione | `POST /payments/bonsct` | `createDelayedPayment()` | ✅ `sct()->execute()` | richiede `simulationId` (5 min) |
| Stato | `GET /payments/bonsct/{iban}/history` | `getDelayedPaymentStatus()` | ✅ `sct()->status()` | filtri: `customerCRO`, `paymentId`, `endToEndId`, `simulationId` (la 2.x supporta solo i primi due) |
| Lista | `GET /payments/bonsct/{iban}/list` | `getDelayedPaymentsList()` | ✅ `sct()->payments()` | `offset` = identificativo di un pagamento |
| Revoca | `GET /payments/bonsct/{iban}/revoke` | `revokeDelayedPayment()` | ✅ `sct()->revoke()` | solo entro i cut-off |
| Simulazione ISO 20022 | `POST /payments/bonsct/simulation/iso` | — | non pianificata | body XML pain.001 |
| Esecuzione ISO 20022 | `POST /payments/bonsct/iso` | — | non pianificata | body XML pain.001 |

## Strumenti IBAN

| Operazione | Endpoint | Metodo 2.x | 3.0 | Note |
|---|---|---|---|---|
| Verifica sintattica | `GET /tools/iban/formal-check` | — | ✅ `ibanTools()->formalCheck()` | campi "numerici" letti come stringhe |
| Esistenza e congruenza con CF/P.IVA | `GET /tools/iban/chargeabilityMulti` | — | ✅ `ibanTools()->verify($iban, $cf, $piva)` | anche banche aderenti a CBI Globe |
| Verifica esistenza | `GET /tools/iban/chargeabilityMulti` | — | ✅ `ibanTools()->verify($iban)` | con il solo IBAN (D-51); la spec scaricata è una copia del formal-check |

## Altre API SmartHub

| API | Stato | Note |
|---|---|---|
| Localizzazione ATM e filiali (`/locator/atm`, `/locator/branch`) | non pianificata | spec disponibili solo in sandbox |
| Servizi di incasso MAV | non pianificata | documentata su SmartHub, spec non scaricata |
