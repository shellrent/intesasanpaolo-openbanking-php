# Architettura

Come è organizzato il codice della 3.0 e che percorso fa una richiesta. Le motivazioni delle scelte sono in [v3/DECISIONS.md](v3/DECISIONS.md), finché quel documento esiste.

> Stato: il core (configurazione, autenticazione, trasporto, date, eccezioni) è completo da T3. La facade e le API arrivano da T4 a T7, e intanto il codice 2.x resta in `src/` (vedi in fondo).

## Struttura

```
src/
├── IntesaSanPaoloClient.php   facade (da T4); oggi è ancora il client 2.x
├── Config/                    configurazione                          [pubblico]
│   ├── ClientConfig           credenziali, IBAN, ambiente, certificato, timeout
│   ├── Environment            Live | Sandbox
│   └── MutualTlsCertificate   certificato mTLS (PEM o PKCS#12)
├── Auth/                      token OAuth2
│   ├── AccessToken            token e scadenza                       [pubblico]
│   ├── TokenStore             condivisione dei token tra istanze     [pubblico]
│   ├── InMemoryTokenStore     implementazione predefinita            [pubblico]
│   └── TokenProvider          richiesta e cache del token            [interno]
├── Http/                      trasporto                               [interno]
│   ├── Sender                 opzioni comuni (mTLS, timeout), errori di rete
│   ├── Transport              chiamate autenticate, envelope, errori API
│   ├── ApiResponse            payload, header, transaction ID
│   ├── Paginator              link "pagina successiva", limiti
│   └── SandboxLinkRewriter    correzione dei link della sandbox
├── Support/                   strumenti                               [interno]
│   ├── DateParser, DateFormat date con formato esplicito
│   ├── Payload                lettura tipizzata e tollerante del JSON
│   └── Clock, SystemClock     ora corrente (sostituibile nei test)
├── Exception/                 eccezioni                               [pubblico]
└── Api/, Request/, Response/, Enum/   da T4 a T7
```

**Pubblico** vuol dire coperto dalla compatibilità semantica: cambia solo in una major. **Interno** (`@internal` nel PHPDoc) vuol dire che può cambiare in qualsiasi versione: non va usato direttamente da chi usa la libreria.

## Percorso di una richiesta

```
API (es. AccountsApi)                     costruisce path, query e body
  └─ Transport::get()/post()
       ├─ TokenProvider::token()           token dallo store, oppure nuovo login
       │    └─ Sender::send(POST token)    Basic auth + mTLS
       └─ Sender::send()                   mTLS, timeout, http_errors=false
            └─ Guzzle ClientInterface      (iniettabile)
       └─ lettura della risposta
            ├─ HTTP 401        → invalida il token, AuthenticationException
            ├─ HTTP >= 400     → ApiException
            ├─ JSON non valido → UnexpectedResponseException
            ├─ success: false  → ApiException
            └─ ApiResponse(payload: Payload, headers, transactionId)
API                                        Payload → DTO di risposta
```

### Regole del trasporto

- **Header**: sono gli stessi della 2.x, che funzionano in produzione: `Authorization: Bearer …` e `apikey: <client ID>` ([api-quirks.md](api-quirks.md), Q-03). `Content-Type: application/json` si aggiunge solo con un body JSON o quando la singola chiamata lo chiede (Q-04). Gli header aggiuntivi non possono sovrascrivere l'autenticazione.
- **mTLS e timeout** si applicano a ogni richiesta, quindi valgono anche con un client Guzzle fornito dall'applicazione.
- **Stati HTTP**: Guzzle non lancia eccezioni per gli stati di errore (`http_errors` disattivato): il `Transport` legge lo stato da sé. In questo modo il comportamento è identico con Guzzle 7 e 8.
- **Nessun retry automatico.** Ripetere un'esecuzione di pagamento potrebbe eseguirla due volte. Dopo una `TransportException` su un pagamento l'esito è sconosciuto, e va verificato con le API di stato prima di riprovare.
- **HTTP 401**: il token in cache viene scartato, così la chiamata successiva rifà il login. La chiamata fallita non viene ripetuta.

## Eccezioni

Tutte implementano `Shellrent\OpenBanking\Exception\OpenBankingException`.

| Eccezione | Quando | Dati utili |
|---|---|---|
| `ConfigurationException` | configurazione non valida (credenziali vuote, IBAN non valido, certificato mancante) | messaggio |
| `TransportException` | la richiesta non si è completata: rete, timeout, TLS | `method`, `uri`; **per i pagamenti l'esito è sconosciuto** |
| `ApiException` | la banca ha rifiutato la richiesta (HTTP ≥ 400 o `success: false`) | `statusCode`, `errors` (`ApiError[]`), `transactionId`, `responseBody` |
| `AuthenticationException` | login rifiutato o HTTP 401 (estende `ApiException`) | come sopra |
| `UnexpectedResponseException` | risposta non interpretabile, campo obbligatorio mancante, tipo inatteso, paginazione anomala | `transactionId` |

`transactionId` è il valore dell'header `X-ISP-Transaction-ID`, da indicare al supporto ISP.

## Token OAuth2

- Il token si rinnova 60 secondi prima della scadenza (`TokenProvider::EXPIRY_MARGIN_SECONDS`).
- Se la risposta non contiene un `expires_in` valido, il token vale per la sola richiesta corrente.
- Per condividere il token tra istanze o processi si implementa `TokenStore` su una cache dell'applicazione:

```php
use Shellrent\OpenBanking\Auth\AccessToken;
use Shellrent\OpenBanking\Auth\TokenStore;

final class LaravelTokenStore implements TokenStore
{
    public function get(string $key): ?AccessToken
    {
        $data = cache()->get($key);

        return is_array($data) ? AccessToken::fromArray($data) : null;
    }

    public function put(string $key, AccessToken $token): void
    {
        cache()->put($key, $token->toArray(), $token->expiresAt);
    }

    public function forget(string $key): void
    {
        cache()->forget($key);
    }
}
```

Il token è una credenziale: lo store non deve essere leggibile da terzi.

## Lettura delle risposte: `Payload`

`Payload` incapsula un oggetto JSON decodificato e fa da unico punto di contatto tra il JSON delle API e i DTO di risposta:
- ogni getter accetta più nomi di campo e usa il primo che ha un valore, così si coprono le varianti come `debtorIBAN`/`debtorIban` (Q-12) e `orderId`/`order-id` (Q-13);
- un campo mancante, `null` o stringa vuota non ha valore e restituisce `null`;
- un valore di tipo sbagliato genera un'`UnexpectedResponseException` con il percorso del campo, per esempio `payload.accountedTransactions[3].amount`;
- le date si leggono solo con un formato esplicito ([dates-and-formats.md](dates-and-formats.md)).

## Logging

L'SDK non ha un logger integrato (D-22). Per registrare le chiamate si passa un client Guzzle con il middleware di log di Guzzle. `transactionId` è disponibile su risposte ed eccezioni.

## Convivenza con il codice 2.x

Fino alla fine di T6 in `src/` restano anche le classi 2.x: `IntesaSanPaoloClient`, `Models/`, `Exceptions/` (al plurale) e `DataSet/`. Sono escluse da PHPStan e Pint, e i loro test stanno in `tests/Unit/Legacy/`. Ogni task elimina le classi 2.x che sostituisce.
