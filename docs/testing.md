# Test

## Comandi

```bash
composer test               # test unitari (PHPUnit)
composer ci                 # validate, formattazione, PHPStan, test unitari: gli stessi controlli della CI
composer test:integration   # test di integrazione contro le API reali, opzionali (vedi sotto)
```

La CI esegue i test su PHP 8.3, 8.4 e 8.5, ciascuno con dipendenze minime (Guzzle 7) e massime (Guzzle 8).

> [!IMPORTANT]
> Se il PHP locale carica l'estensione PECL `psr` (`php -m | grep psr`), Guzzle 8 va in errore fatale e PHPStan legge male i tipi PSR-7. In quel caso lancia i controlli con un PHP senza l'estensione, per esempio `php8.4 $(command -v composer) ci` (D-20).

## Struttura

```
tests/
├── Fixtures/              risposte JSON fittizie per area (es. Accounts/balance.json)
├── Support/
│   ├── MockHttp.php       client Guzzle con MockHandler che registra le richieste
│   └── FrozenClock.php    orologio fermo e spostabile in avanti
├── Unit/
│   ├── TestCase.php       base: config e certificati di prova (file temporanei)
│   └── Api/  Auth/  Config/  Enum/  Exception/  Http/  Request/  Response/  Support/
└── Integration/           chiamate reali, opzionali
    ├── IntegrationTestCase.php   impostazioni, certificato, blocco delle scritture in Live
    ├── SandboxTest.php           casi di test della sandbox
    └── LiveReadOnlyTest.php      verifiche di sola lettura in Live
```

## Regole

- **Nessuna chiamata di rete** nei test unitari. Le richieste HTTP passano sempre da `MockHttp`.
- **Dati deterministici e fittizi.** Niente generatori casuali. Gli IBAN di prova sono segnaposto evidenti (`IT00X0000000000000000000001`).
- **Nessun dato reale.** Le fixture ricavate da risposte reali vanno anonimizzate: IBAN, nomi, importi, CRO, ID.
- **Fixture realistiche.** Struttura e formati devono essere quelli dell'API (spec e quirk), per esempio date `Ymd` per i movimenti e `nextPage` con il solo `offsetid`. Una fixture per scenario, in `tests/Fixtures/<Area>/`.
- **I quirk hanno un test.** Ogni comportamento documentato in [api-quirks.md](api-quirks.md) che il codice gestisce ha un test che cita la voce (es. "Q-34: …") nel PHPDoc.
- **I certificati sono file temporanei vuoti** (`TestCase::temporaryFile()`): il MockHandler non li legge. Non vanno committati certificati, nemmeno finti: `.gitignore` esclude `*.pem`, `*.key`, `*.p12`.

## `MockHttp`

```php
$http = new MockHttp(
    MockHttp::token('token-123'),                            // risposta del login OAuth2
    MockHttp::envelope(['currency' => 'EUR'], [              // { success: true, errors: null, payload: {...} }
        'X-ISP-Transaction-ID' => 'TX-1',
    ]),
    MockHttp::fixture('Accounts/balance'),                  // body di tests/Fixtures/Accounts/balance.json
);

// ... codice che usa $http->client ...

$http->request(1);      // richiesta PSR-7 registrata (0 = login)
$http->options(1);      // opzioni Guzzle con cui è stata inviata (cert, timeout, ...)
$http->jsonBody(1);     // body JSON decodificato
$http->count();         // numero di richieste ricevute
```

Per simulare errori di rete si mette in coda un'eccezione Guzzle, per esempio `new ConnectException('…', new Request('GET', '…'))`.

## Test di integrazione

La suite `integration` chiama le API reali. Non fa parte di `composer test` né della CI, e ogni test viene saltato se manca la configurazione.

Le impostazioni si leggono dalle variabili d'ambiente oppure dal file `env/integration.env`, escluso da git; il modello è [`env/integration.env.example`](../env/integration.env.example). Le variabili d'ambiente hanno la precedenza.

| Variabile | Uso |
|---|---|
| `ISP_ENVIRONMENT` | `sandbox` oppure `live`: sceglie quale classe di test eseguire |
| `ISP_CLIENT_ID`, `ISP_CLIENT_SECRET` | client OAuth2 dell'app SmartHub, distinto per sandbox e Live |
| `ISP_CERTIFICATE` | certificato mTLS: file `.p12`, oppure PEM insieme a `ISP_PRIVATE_KEY` (il `.p12` di SmartHub va convertito, vedi il README) |
| `ISP_PRIVATE_KEY`, `ISP_CERTIFICATE_PASSPHRASE` | chiave PEM e passphrase |
| `ISP_LIVE_READ_ONLY` | solo Live: `yes` conferma l'esecuzione di sola lettura |
| `ISP_IBAN` | solo Live: il conto da leggere |
| `ISP_VAT_NUMBER`, `ISP_FISCAL_CODE` | solo Live, facoltativi: intestatario di `ISP_IBAN`, per la verifica di congruenza |

```bash
composer test:integration
```

### Sandbox

`SandboxTest` usa i dati fittizi dei casi di test delle spec (IBAN `…XTESTXCASEX…`): la sandbox risponde solo a quelli (Q-51). Comprende saldo, movimenti, esecuzione sospesa (`PDNG`), stati, liste, revoca, strumenti IBAN e raggiungibilità. I casi di simulazione ed esecuzione con un `endToEndId` di 36 caratteri non si possono eseguire con l'SDK (D-56).

### Live, sola lettura

`LiveReadOnlyTest` legge saldo, movimenti, liste e stati dei pagamenti, verifiche IBAN e raggiungibilità del conto `ISP_IBAN`, con le credenziali di produzione (D-55).
- **Non sposta denaro**: il client dei test blocca, prima dell'invio, ogni richiesta che non sia una `GET`, tranne quella del token.
- Le asserzioni non confrontano dati reali con valori attesi, così un fallimento non li stampa.
- Si lancia solo su richiesta esplicita del maintainer. Una simulazione in Live non sposta denaro, ma resta fuori dalla suite e richiede un'approvazione a parte.
