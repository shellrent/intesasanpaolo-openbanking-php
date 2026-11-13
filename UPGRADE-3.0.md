# Aggiornamento dalla 2.x alla 3.0

La 3.0 è una riscrittura e non è compatibile con la 2.x: la configurazione, le eccezioni e i model di risposta cambiano.

## Requisiti

| | 2.x | 3.0 |
|---|---|---|
| PHP | ^8.0 | **^8.3** |
| Guzzle | tramite `php-http/guzzle7-adapter` | **`guzzlehttp/guzzle` ^7.15.2 o ^8.0.1**, dipendenza diretta |
| Certificato mTLS | opzionale | **obbligatorio** |

Con l'estensione PECL `psr` attiva serve Guzzle 7: le interfacce dell'estensione sono incompatibili con Guzzle 8.

## Configurazione

Nella 2.x il client si configurava con argomenti del costruttore e `enableMutualAuthentication()`. Nella 3.0 c'è un oggetto di configurazione:

```php
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Config\MutualTlsCertificate;

$config = new ClientConfig(
    clientId: '…',
    clientSecret: '…',
    iban: 'IT…',
    certificate: MutualTlsCertificate::pkcs12('/path/certificate.p12', 'passphrase'),
    // oppure: MutualTlsCertificate::pem('/path/cert.pem', '/path/key.pem', 'key passphrase'),
    environment: Environment::Live,   // il default è Environment::Sandbox
);
```

| 2.x | 3.0 |
|---|---|
| `new IntesaSanPaoloClient($id, $secret, $iban, $live)` | `new IntesaSanPaoloClient(new ClientConfig(…))` |
| `$live = true/false` | `Environment::Live` / `Environment::Sandbox`, default **Sandbox** |
| `enableMutualAuthentication($cert, $pass, $key, $keyPass)` | `MutualTlsCertificate::pem()` / `::pkcs12()`, obbligatorio |
| `disableMutualAuthentication()` | rimosso: tutte le API richiedono mTLS |
| nessun timeout | `connectTimeout` (10 s) e `timeout` (60 s) configurabili |

Altre differenze:
- i file del certificato vengono controllati alla creazione della configurazione;
- un file PKCS#12 deve avere estensione `.p12`;
- il `.p12` scaricato da SmartHub usa una cifratura che OpenSSL 3 non legge, e va convertito una volta: i comandi sono nel README, sezione "Certificato";
- l'IBAN viene normalizzato (maiuscole, senza spazi) e validato nel formato.

## Eccezioni

| 2.x | 3.0 |
|---|---|
| `Shellrent\OpenBanking\Exceptions\Exception` | `Shellrent\OpenBanking\Exception\OpenBankingException` (interfaccia comune) |
| `Shellrent\OpenBanking\Exceptions\HttpException` (solo 4xx) | `ApiException` (4xx, 5xx, `success: false`) e `AuthenticationException` (login, 401) |
| eccezioni Guzzle grezze per 5xx e rete | `TransportException`: **per i pagamenti l'esito è sconosciuto** |
| model vuoti o warning PHP su risposte anomale | `UnexpectedResponseException`: anche in questo caso, **per i pagamenti l'esito è sconosciuto** |

Il namespace cambia da `Exceptions` (plurale) a `Exception` (singolare). Le eccezioni API espongono `statusCode`, `errors` (lista di `ApiError`) e `transactionId`, cioè il valore dell'header `X-ISP-Transaction-ID` da indicare al supporto ISP.

## Date

- Le date restituite sono `DateTimeImmutable` (nella 2.x erano `DateTime`) e le date senza orario sono a mezzanotte in `Europe/Rome`.
- Corretto il parsing delle date `d/m/Y`: nella 2.x i giorni da 1 a 12 delle date di esecuzione SCT erano letti con giorno e mese invertiti.
- Dettagli in `docs/dates-and-formats.md`.

## Token OAuth2

- Il token si può condividere tra istanze con un `TokenStore` (vedi `docs/architecture.md`). Nella 2.x ogni istanza rifaceva il login.
- Il token si rinnova 60 secondi prima della scadenza.

## Client

```php
$client = new IntesaSanPaoloClient($config);                        // client Guzzle predefinito
$client = new IntesaSanPaoloClient($config, $guzzle, $tokenStore);  // client Guzzle e TokenStore propri
```

- Le API si raggiungono per area: `$client->accounts()`, `sctInstant()`, `sct()`, `ibanTools()`.
- Le risposte sono oggetti immutabili con **proprietà pubbliche** al posto dei getter: `$balance->getAvailableBalance()` diventa `$balance->availableBalance`.

## Conti

| 2.x | 3.0 |
|---|---|
| `getBalance(DateTime $date)` → `Models\Balance` | `accounts()->balance(?DateTimeInterface $date = null)` → `Response\Accounts\Balance` (data opzionale: senza data è il saldo di oggi) |
| `getTransactions(DateTime $date)` → `Models\Collections\Transactions` | `accounts()->transactions(DateTimeInterface $date)` → `Response\Accounts\TransactionList` |
| `getTodayTransactions()` → `Models\Collections\Transactions` | `accounts()->todayTransactions()` → `Response\Accounts\TransactionList` |

### `Balance`

I getter diventano le proprietà `currency`, `availableBalance`, `accountingBalance`, `creditLine`, `inquiryDate` ed `executionDate`, tutte nullable. Nuova proprietà: `transactionId`.

### `Transactions` → `TransactionList`

| 2.x | 3.0 |
|---|---|
| `getAccountedTransactions()` | `accounted` |
| `getUnaccountedTransactions()` | `unaccounted` |
| `getTotalAccountedTransactions()` | `totalAccounted` (null se l'API non lo indica) |
| `getTotalUnaccountedTransactions()` (0 se assente) | `totalUnaccounted` (null se assente) |
| `getSIACode()` | `siaCode` |
| `getInitialAccountingBalance()`, `getFinalAccountingBalance()`, `getLiquidBalance()` (stringhe) | `initialAccountingBalance`, `finalAccountingBalance`, `liquidBalance` (**float**) |
| `getBankCode()`, `getCustomerCode()`, `getAccountType()`, `getAccountCurrency()`, `getAvailableBalance()`, `getCreditLine()` | proprietà con lo stesso nome |
| `getInquiryDate()`, `getExecutionDate()` | `inquiryDate`, `executionDate` |
| — | `iban`, `transactionIds` (uno per pagina), `isComplete()` |

`isComplete()` dice se sono arrivati tutti i movimenti contabilizzati dichiarati dalla banca: conviene controllarlo prima di considerare completa la giornata.

### `Transaction`

| 2.x | 3.0 |
|---|---|
| `getReason()` | **`additionalInfo`** (il nome del campo dell'API) |
| `getStatus()` (stringa) | `status` (enum `TransactionStatus`, null se il valore è sconosciuto) e `rawStatus` (stringa originale) |
| `getValueDate()`: **data corrente** se l'API non la indica | `valueDate`: **null** se l'API non la indica, o se manda `""` o `"0"` |
| `getAmount()`: 0.0 se l'importo manca | `amount`: un movimento senza importo genera un'`UnexpectedResponseException` |
| `getUniqueId()`, `getReference()`, `getDate()`, `getAccountingDate()`, `getCurrency()`, `getOriginalAmount()`, `getOriginalCurrency()`, `getExchangeRate()`, `getType()`, `getDescription()` | proprietà con lo stesso nome |

Nella 2.x i campi testuali assenti diventavano stringhe vuote; nella 3.0 sono `null`.

## SCT Instant

| 2.x | 3.0 |
|---|---|
| `new PaymentExecution()` + setter | `new InstantPayment(creditorName, creditorIban, amount, paymentInformation, debtorName, …)`, immutabile e validata |
| `simulateInstantPayment($execution)` → `PaymentSimulated` | `sctInstant()->simulate($payment)` → `InstantSimulation` |
| `createInstantPayment($execution)` con `setSimulationId()` | `sctInstant()->execute($payment, $simulation, acceptVopResult: false)` → `InstantExecution` |
| `createInstantPayment()` con `setResubmit(true)` + `setResubmitId()` | `sctInstant()->resubmit($payment, $simulationId, $resubmitId)` |
| `getPaymentStatus($orderId, $paymentId)` → `PaymentStatus` | `sctInstant()->status($orderId, $paymentId)` → `InstantPaymentDetails` |
| `getPaymentsList($from, $to)` → `PaymentInfos` | `sctInstant()->payments($from, $to)` → `InstantPaymentList` |

### Comportamenti nuovi

- **Blocco VoP**: `execute()` richiede l'oggetto simulazione e, se l'esito VoP non è un match pieno (compreso un esito mancante), lancia `VopConfirmationRequiredException` finché non si passa `acceptVopResult: true`. Per eseguire in un altro processo si salva la simulazione con `toArray()` e la si ricarica con `InstantSimulation::fromArray()`.
- **Controlli sulla simulazione**: `execute()` rifiuta una simulazione non accettata, o fatta per un altro IBAN, un altro nome del creditore o un altro importo (`InvalidRequestException`). Per pagare con il nome suggerito da un close match si simula di nuovo.
- **Validazione della richiesta**:
  - campi obbligatori;
  - nomi al massimo 70 caratteri, causale 140, `endToEndId` 36 (per esempio un UUID);
  - IBAN validi;
  - importo positivo con al massimo 2 decimali.

  I caratteri dei testi non sono controllati, come nella 2.x; c'è l'helper opzionale `SepaText::sanitize()`.
- **IBAN del debitore**: è opzionale, e il default è quello della configurazione.
- **`endToEndId`**: `""` e `NOTPROVIDED` valgono come assenti, sia in richiesta sia in risposta.
- **Resubmit**: invia sempre `resubmit: true` insieme a `resubmitId`. Nella 2.x `setResubmitId()` senza `setResubmit(true)` non aveva effetto.

### Model → DTO

| 2.x | 3.0 |
|---|---|
| `PaymentSimulated::getValidationStatus()` (stringa: `ACSC`, `ACCP`, `ACSC acquired`, …) | `InstantSimulation::$validationStatus` (`ValidationStatus::Accepted`/`Rejected`) e `$rawValidationStatus`; `isAccepted()` |
| — | `InstantSimulation::$vop` (`VopResult`: `result`, `matchedName`, `description`) |
| `PaymentExecuted::getPaymentStatus()` (stringa) | `InstantExecution::$paymentStatus` (`PaymentStatus`: `Executed`, `Pending`, `ToVerify`, `Rejected`, …) e `$rawPaymentStatus`; `canBeResubmitted()` |
| `getOrderId()`, `getPaymentId()`, `getCustomerCro()`, `getResubmitId()`, `getEndToEndId()` | `orderId`, `paymentId`, `customerCro`, `resubmitId`, `endToEndId` |
| `getDebtorName()`, `getDebtorIban()`, `getCreditorBic()`, `getUltimateCreditorName()`, … | `debtor` e `creditor` (`Party`: `name`, `iban`, `bic`, `ultimateName`, `nation`, `address`) |
| `PaymentStatus::getStatus()` (stringa) | `InstantPaymentDetails::$status` (`InstantStatus`: `Completed`, `Canceled`, `Rejected`, `Returned`) e `$rawStatus` |
| `PaymentInfos::getPayments()` | `InstantPaymentList::$payments` (`InstantPaymentListItem`), `$totalCount`, `isComplete()` |

## SCT (bonifico ordinario)

Nella 2.x i metodi avevano il suffisso "Delayed"; nella 3.0 l'area si chiama `sct()`, come nella terminologia ISP.

| 2.x | 3.0 |
|---|---|
| `new PaymentExecution()` + `setCurrency()` + `setRequestedExecutionDate()` | `new SctPayment(creditorName, creditorIban, amount, paymentInformation, debtorName, …)`: valuta EUR e categoryPurpose CASH di default, data di esecuzione opzionale |
| `simulateDelayedPayment($execution)` → `PaymentSimulated` | `sct()->simulate($payment)` → `SctSimulation` |
| `createDelayedPayment($execution)` con `setSimulationId()` | `sct()->execute($payment, $simulation, acceptVopResult: false)` → `SctExecution` |
| `getDelayedPaymentStatus($cro, $paymentId)` → `PaymentStatus` | `sct()->status(customerCro:, paymentId:, endToEndId:, simulationId:)` → `SctPaymentDetails` |
| `getDelayedPaymentsList($from, $to)` → `PaymentInfos` | `sct()->payments($from, $to)` → `SctPaymentList` |
| `revokeDelayedPayment($cro, $paymentId)` → `DelayedPaymentRevoke` | `sct()->revoke($cro, $paymentId)` → `SctRevocation` |

### Comportamenti nuovi

- **Stesse regole dell'SCT Instant**: blocco VoP in `execute()`, controllo che la simulazione appartenga al pagamento, validazione della richiesta, simulazione salvabile.
- **`categoryPurpose` configurabile**: nella 2.x era sempre `CASH`, che resta il default.
- **Data di esecuzione opzionale**: senza data il campo non viene inviato. La 2.x la richiedeva sempre e andava in `TypeError` se mancava. Per mantenere il comportamento del gestionale si passa la data di oggi.
- **Date `d/m/Y` corrette**: nella 2.x `getExecutionDate()` scambiava giorno e mese per i giorni da 1 a 12.
- **Stato per `endToEndId` e `simulationId`**: utile per ricostruire l'esito dopo un errore di rete.

### Model → DTO

| 2.x | 3.0 |
|---|---|
| `PaymentSimulated::getValidationStatus()` | `SctSimulation::$validationStatus` (`ValidationStatus`) e `$rawValidationStatus`; `isAccepted()`; `$vop` |
| `PaymentExecuted::getCommissions()` (solo `commissioni`) | `SctExecution::$fees` / `SctSimulation::$fees` (`fees` oppure `commissioni`) |
| `PaymentExecuted::getSettlemenDate()` (refuso, sempre vuoto) | `$settlementDate` |
| `PaymentExecuted::getRevokeDate()`, `getRevokeTime()` | `$revokeDate` (data e ora), `$revokeTime` |
| `PaymentExecuted::getPaymentStatus()` (stringa) | `SctExecution::$paymentStatus` (`PaymentStatus`: `InProgress`, `Accepted`, `ToVerify`, `Rejected`) e `$rawPaymentStatus` |
| `PaymentExecuted::getCustomerCro()` | `SctExecution::$customerCro` |
| `PaymentInfos::getPayments()` (senza CRO) | `SctPaymentList::$payments` (`SctPaymentListItem` con `customerCro`, `endToEndId`, `simulationId`, `categoryPurpose`, date) |
| `DelayedPaymentRevoke::getStatus()` (`OK`/`KO`) | `SctRevocation::isRevoked()`, `$revoked`, `$rawStatus`; una revoca rifiutata è un esito (`revoked: false`), anche quando la banca risponde `success: false` |

## Classi 2.x rimosse

Tutte le classi della 2.x sono state eliminate:
- `Shellrent\OpenBanking\Models\*` e `Models\Collections\*` → DTO in `Response\*` e richieste in `Request\*`;
- `Shellrent\OpenBanking\Exceptions\*` → `Shellrent\OpenBanking\Exception\*`;
- `Shellrent\OpenBanking\DataSet\CurrencySet` → non serve più: l'SCT è in EUR, e la valuta di `SctPayment` è un codice ISO 4217.

## Strumenti IBAN (novità)

Nella 2.x non c'erano. Nella 3.0:

| API | Metodo | Risultato |
|---|---|---|
| Esistenza e congruenza con l'intestatario | `ibanTools()->verify($iban, fiscalCode: …, vatNumber: …)`, almeno uno dei due | `IbanVerification`: `isAllowed()`, `additionalInfo`, `isTransferred()` |
| Verifica sintattica, solo IBAN Intesa Sanpaolo | `ibanTools()->formalCheck($iban)` | `IbanFormalCheck`: `isValid()`, `countryCode`, `checkDigits`, `bankCode`, `bankAgency`, `account`, `bban` |
| Raggiungibilità SCT Instant | `sctInstant()->reachability($iban)` | `InstantReachability`: `isReachable()`, `status`, `bic` |

Note:
- **Formal-check**: in Live risponde solo per gli IBAN Intesa Sanpaolo e non verifica l'esistenza del conto; per gli altri IBAN la banca risponde con un errore (`ApiException`, codice `WI01`).
- **Input malformati**: `verify()` e `reachability()` rifiutano prima della chiamata IBAN, codici fiscali e partite IVA malformati, e `verify()` senza codice fiscale né partita IVA.
- **Errori**: un IBAN estero (`WI03`) o di una banca non aderente al servizio (`CHK-IBAN_01.001.A0014`) danno un'`ApiException`.
- **Esito negativo**: è un risultato (`isAllowed()` falso), non un'eccezione, anche quando la banca risponde `success: false`.
- **Esistenza**: non c'è una verifica con il solo IBAN, perché l'API la rifiuta. Un conto inesistente risulta da `verify()` come esito negativo (D-64, in `docs/decisions.md`).
