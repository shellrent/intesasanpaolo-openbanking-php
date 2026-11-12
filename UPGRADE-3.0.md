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
| `new IntesaSanPaoloClient($id, $secret, $iban, $live)` | `new ClientConfig(…)` passato alla facade (da T4) |
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

## Metodi e model

Da completare in T4–T7: corrispondenza tra metodi 2.x e API 3.0, nuovi DTO di risposta, stati come enum, esito VoP.
