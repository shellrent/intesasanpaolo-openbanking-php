# Aggiornamento dalla 2.x alla 3.0

> **Bozza:** la 3.0 è in sviluppo sul branch `3.x`. Questa guida si completa man mano che le API vengono riscritte (roadmap in `docs/v3/ROADMAP.md`).

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
- l'IBAN viene normalizzato (maiuscole, senza spazi) e validato nel formato.

## Eccezioni

| 2.x | 3.0 |
|---|---|
| `Shellrent\OpenBanking\Exceptions\Exception` | `Shellrent\OpenBanking\Exception\OpenBankingException` (interfaccia comune) |
| `Shellrent\OpenBanking\Exceptions\HttpException` (solo 4xx) | `ApiException` (4xx, 5xx, `success: false`) e `AuthenticationException` (login, 401) |
| eccezioni Guzzle grezze per 5xx e rete | `TransportException`: **per i pagamenti l'esito è sconosciuto** |
| model vuoti o warning PHP su risposte anomale | `UnexpectedResponseException` |

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

- Le API si raggiungono per area: `$client->accounts()`, poi (T5–T7) `sctInstant()`, `sct()`, `ibanTools()`.
- Le risposte sono oggetti immutabili con **proprietà pubbliche** al posto dei getter: `$balance->getAvailableBalance()` diventa `$balance->availableBalance`.
- Finché i pagamenti non sono riscritti, il client 2.x resta disponibile come `Shellrent\OpenBanking\Legacy\IntesaSanPaoloClient`, senza i metodi dei conti. Verrà eliminato prima del rilascio 3.0.0.

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

## Pagamenti, strumenti IBAN

Da completare in T5–T7.
