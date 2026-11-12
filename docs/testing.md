# Test

## Comandi

```bash
composer test     # solo PHPUnit
composer ci       # validate, formattazione, PHPStan, PHPUnit: gli stessi controlli della CI
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
└── Unit/
    ├── TestCase.php       base: config e certificati di prova (file temporanei)
    └── Api/  Auth/  Config/  Enum/  Exception/  Http/  Request/  Response/  Support/
```

## Regole

- **Nessuna chiamata di rete.** Le richieste HTTP passano sempre da `MockHttp`.
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

## Test di integrazione in sandbox

I test opzionali contro la sandbox arrivano in T8. Useranno credenziali e certificato locali (cartella `env/`, esclusa da git) e partiranno solo se richiesti esplicitamente. Non devono mai chiamare l'ambiente Live.
