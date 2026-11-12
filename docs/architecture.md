# Architettura

Come è organizzato il codice della 3.0 e che percorso fa una richiesta. Le motivazioni delle scelte sono in [decisions.md](decisions.md).

Per aggiungere un'API: [adding-an-endpoint.md](adding-an-endpoint.md).

## Struttura

```
src/
├── IntesaSanPaoloClient.php   facade: accounts(), sctInstant(), sct(), ibanTools() [pubblico]
├── Api/                       un oggetto per area: AccountsApi, SctInstantApi, SctApi, IbanToolsApi [pubblico]
│   └── PaymentGuard           controlli prima dell'esecuzione (VoP, simulazione) [interno]
├── Response/                  DTO readonly di risposta, per area             [pubblico]
│   ├── Accounts/              Balance, Transaction, TransactionList
│   ├── SctInstant/            InstantSimulation, InstantExecution, InstantPaymentDetails, InstantPaymentList(Item), InstantReachability
│   ├── Sct/                   SctSimulation, SctExecution, SctPaymentDetails, SctPaymentList(Item), SctRevocation
│   ├── Iban/                  IbanFormalCheck, IbanVerification
│   ├── Party                  debitore o creditore
│   └── VopResult              esito della Verification of Payee (dagli header)
├── Enum/                      stati come enum tolleranti (FromApiValue)     [pubblico]
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
│   ├── Json, Url, EndToEndId  JSON deterministico, link, normalizzazione end-to-end ID
│   ├── SepaText               helper opzionale per i testi SEPA       [pubblico]
│   └── Clock, SystemClock     ora corrente (sostituibile nei test)
├── Exception/                 eccezioni                               [pubblico]
└── Request/                   richieste di pagamento validate: InstantPayment, SctPayment [pubblico]
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
- **Nessun retry automatico.** Ripetere un'esecuzione di pagamento potrebbe eseguirla due volte. Dopo una `TransportException`, una `UnexpectedResponseException` o un'`ApiException` con stato 5xx su un pagamento l'esito è sconosciuto, e va verificato con le API di stato prima di riprovare.
- **HTTP 401**: il token in cache viene scartato, così la chiamata successiva rifà il login. La chiamata fallita non viene ripetuta.

## Risposte

- I DTO di risposta (`Response/`) sono classi `final readonly` con proprietà pubbliche che portano i nomi dei campi dell'API (es. `additionalInfo`, `siaCode`). Si costruiscono da un `Payload` con `fromPayload()`, che è `@internal`.
- Gli stati sono enum costruiti con `fromApi()` (trait `Enum\FromApiValue`): la conversione ignora maiuscole e spazi, considera solo la prima parola e conosce gli alias. Un valore sconosciuto dà `null`, e il DTO conserva sempre anche il valore originale (`rawStatus`).
- Le API paginate raccolgono tutte le pagine prima di restituire il risultato. Le liste espongono i totali dichiarati dalla banca, un metodo `isComplete()` e il transaction ID di ogni pagina.

## Richieste

Le richieste di pagamento (`Request/`) sono classi `final readonly` validate e normalizzate alla creazione: IBAN in maiuscolo e senza spazi, importo arrotondato a 2 decimali, `endToEndId` vuoto o `NOTPROVIDED` trasformato in `null`. Un valore non valido genera un'`InvalidRequestException` prima di qualsiasi chiamata. Il corpo JSON lo costruisce l'API (es. `SctInstantApi`), che applica le differenze tra Live e sandbox. La codifica JSON è deterministica (`Support\Json`), indipendente da `serialize_precision`.

## Eccezioni

Tutte implementano `Shellrent\OpenBanking\Exception\OpenBankingException`.

| Eccezione | Quando | Dati utili |
|---|---|---|
| `ConfigurationException` | configurazione non valida (credenziali vuote, IBAN non valido, certificato mancante) | messaggio |
| `TransportException` | la richiesta non si è completata: rete, timeout, TLS | `method`, `uri`; **per i pagamenti l'esito è sconosciuto** |
| `ApiException` | la banca ha rifiutato la richiesta (HTTP ≥ 400 o `success: false`) | `statusCode`, `errors` (`ApiError[]`), `transactionId`, `responseBody` |
| `AuthenticationException` | login rifiutato o HTTP 401 (estende `ApiException`) | come sopra |
| `InvalidRequestException` | dati della richiesta non validi (non inviata), simulazione non accettata o di un altro pagamento | messaggio |
| `VopConfirmationRequiredException` | esecuzione bloccata: l'esito VoP non è un match pieno e non è stato accettato | `vop` (`VopResult`) |
| `UnexpectedResponseException` | risposta non interpretabile, campo obbligatorio mancante, tipo inatteso, paginazione anomala | `transactionId`; **per i pagamenti l'esito è sconosciuto** |

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
- un campo mancante, `null`, stringa vuota o stringa `"null"` (Q-50) non ha valore e restituisce `null`;
- un valore di tipo sbagliato genera un'`UnexpectedResponseException` con il percorso del campo, per esempio `payload.accountedTransactions[3].amount`;
- le risposte delle esecuzioni di pagamento si leggono in modalità tollerante (`lenient()`): un valore di tipo o formato sbagliato vale `null`, perché a quel punto la banca ha già agito e l'esito e gli identificativi non devono andare persi (D-61);
- i link di paginazione si leggono con `link()`, che accetta sia una URL sia un oggetto `{"next": …}` (Q-41…Q-43);
- le date si leggono solo con un formato esplicito ([dates-and-formats.md](dates-and-formats.md)).

## Logging

L'SDK non ha un logger integrato (D-22). Per registrare le chiamate si passa un client Guzzle con il middleware di log di Guzzle. `transactionId` è disponibile su risposte ed eccezioni.

## Sicurezza del trasporto

- Le credenziali e il certificato vanno solo all'host delle API ISP: un link di paginazione verso un altro host (per esempio `http://localhost:8081` in Live) genera un'`UnexpectedResponseException` e non viene seguito (D-63).
- I redirect non vengono mai seguiti.
- I parametri di `Sender::send()` che portano le credenziali (header, body) sono marcati `#[SensitiveParameter]` e non compaiono negli argomenti degli stack trace.
- L'eccezione Guzzle originale, disponibile come `getPrevious()` di una `TransportException`, contiene invece la richiesta con i suoi header: chi invia le eccezioni a un servizio esterno non deve serializzarne la richiesta.
- I messaggi delle eccezioni non contengono segreti.
