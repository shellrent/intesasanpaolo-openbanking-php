# Intesa Sanpaolo Open Banking – PHP SDK

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shellrent/intesasanpaolo-openbanking-php.svg?style=flat-square)](https://packagist.org/packages/shellrent/intesasanpaolo-openbanking-php)
[![License](https://img.shields.io/packagist/l/shellrent/intesasanpaolo-openbanking-php.svg?style=flat-square)](LICENSE.md)

SDK PHP **non ufficiale** per le API [SmartHub](https://smarthub.intesasanpaolo.com/it/homepage/documenti-sviluppo.html) di Intesa Sanpaolo, dedicate a conti correnti e pagamenti delle aziende.

> [!IMPORTANT]
> **La 3.0 è una riscrittura e non è compatibile con la 2.x.** Per aggiornare segui [UPGRADE-3.0.md](UPGRADE-3.0.md). La 2.x resta installabile con `composer require shellrent/intesasanpaolo-openbanking-php:^2.1`.

## Funzionalità

| Area | API | 2.x | 3.0 |
|---|---|:---:|:---:|
| Conti | saldo, movimenti di fine giornata e intraday | ✅ | ✅ |
| SCT Instant | simulazione (con esito VoP), esecuzione, resubmit, stato, lista | ✅ | ✅ |
| SCT (bonifico ordinario) | simulazione (con esito VoP), esecuzione, stato, lista, revoca | ✅ | ✅ |
| Strumenti | verifica IBAN (sintattica per IBAN ISP, esistenza e congruenza con CF/P.IVA), raggiungibilità SCT Instant | — | ✅ |

Il dettaglio per endpoint è in [docs/api-coverage.md](docs/api-coverage.md).

## Requisiti

- PHP 8.3 o superiore (la 2.x richiede PHP 8.0), con estensione `json`.
- Guzzle 7.15.2+ oppure Guzzle 8.0.1+.

  > [!NOTE]
  > Se il tuo PHP carica l'estensione PECL `psr` (frequente negli ambienti Phalcon), usa Guzzle 7: le interfacce dell'estensione sono incompatibili con Guzzle 8 (psr7 3.x).
- Un'app registrata su SmartHub con le API necessarie attive, e le relative credenziali (client ID e client secret).
- Il certificato di mutua autenticazione (mTLS), che si scarica dalla sezione *Certificates Management* dell'area riservata SmartHub.

## Installazione

```bash
composer require shellrent/intesasanpaolo-openbanking-php
```

## Ambienti e autenticazione

- **Sandbox e Live**: le API sono identiche, ma la sandbox restituisce dati fittizi. Si sviluppa e si prova in sandbox, poi si passa a Live.
- **OAuth2**: l'SDK ottiene e rinnova da sé il token (flusso *client credentials*).
- **Mutua autenticazione**: le API richiedono un certificato client, in formato PEM con chiave privata oppure PKCS#12.
  - Lo stesso certificato vale per Live e sandbox.
  - Dura un anno, e se ne possono avere al massimo due attivi: genera e installa il nuovo prima che scada il vecchio.

### Certificato

Dalla funzione *Certificates Management* di SmartHub si scarica un file **PKCS#12** (`.p12`, certificato e chiave privata insieme) con la sua passphrase. Il certificato vale un anno, è lo stesso per Live e sandbox, e se ne possono avere al massimo due attivi: al rinnovo si ripete la stessa procedura.

**1. Verifica se il tuo PHP lo legge.** Il `.p12` di SmartHub usa una cifratura legacy (RC2-40) che **OpenSSL 3** non legge, e PHP/cURL usano l'OpenSSL del sistema:

```bash
php -r 'echo curl_version()["ssl_version"], PHP_EOL;'   # es. "OpenSSL/3.0.13": serve la conversione
openssl pkcs12 -in certificato.p12 -noout                # con OpenSSL 3: errore "unsupported … RC2-40-CBC"
```

Con OpenSSL 3 il `.p12` originale non funziona: la richiesta fallisce con `cURL error 58: could not parse PKCS12 file`, anche se la passphrase è giusta.

**2. Scegli come usarlo:**

| Cosa hai | Cosa fare | Configurazione |
|---|---|---|
| `.p12` di SmartHub, PHP con OpenSSL 1.1 | niente | `MutualTlsCertificate::pkcs12('/path/certificato.p12', 'passphrase')` |
| `.p12` di SmartHub, PHP con OpenSSL 3 (il caso più comune) | convertirlo una volta, in uno dei due formati qui sotto | vedi sotto |
| certificato e chiave privata già in PEM (file separati) | niente | `MutualTlsCertificate::pem('/path/certificato.pem', '/path/chiave.pem', 'passphrase della chiave')`, senza passphrase se la chiave non è cifrata |
| file `.pfx` | è un PKCS#12: rinominalo in `.p12`, perché Guzzle riconosce il tipo dall'estensione | come per il `.p12` |

Conversione in un **PKCS#12 con cifratura moderna** (AES-256), la più semplice: resta un solo file con passphrase.

```bash
# chiede la passphrase attuale e poi quella del nuovo file
openssl pkcs12 -legacy -in certificato.p12 -nodes | openssl pkcs12 -export -out certificato-aes.p12
```

```php
MutualTlsCertificate::pkcs12('/path/certificato-aes.p12', 'passphrase del nuovo file');
```

Oppure conversione in **PEM**, con certificato e chiave privata in due file:

```bash
openssl pkcs12 -legacy -in certificato.p12 -clcerts -nokeys -out certificato.pem
openssl pkcs12 -legacy -in certificato.p12 -nocerts -out chiave.pem   # chiave cifrata: chiede una passphrase
```

```php
MutualTlsCertificate::pem('/path/certificato.pem', '/path/chiave.pem', 'passphrase della chiave');
```

Aggiungendo `-nodes` al secondo comando la chiave viene salvata in chiaro, e nella configurazione si omette la passphrase. In ogni formato il file con la chiave privata va protetto come l'originale: permessi `600`, mai nel repository.

## Verification of Payee (VoP)

Dal 2025 la simulazione di un bonifico (SCT e SCT Instant) restituisce anche l'esito della verifica del beneficiario, cioè la corrispondenza tra IBAN e nome. **Eseguire il bonifico equivale ad accettare quell'esito.** La 3.0 lo espone nel risultato della simulazione (`$simulation->vop`) e **esegue in automatico solo con un match pieno**; negli altri casi serve una conferma esplicita (`acceptVopResult: true`).

## Utilizzo

```php
use Shellrent\OpenBanking\Config\ClientConfig;
use Shellrent\OpenBanking\Config\Environment;
use Shellrent\OpenBanking\Config\MutualTlsCertificate;
use Shellrent\OpenBanking\IntesaSanPaoloClient;
use Shellrent\OpenBanking\Request\InstantPayment;

$client = new IntesaSanPaoloClient(new ClientConfig(
    clientId: '…',
    clientSecret: '…',
    iban: 'IT…',
    certificate: MutualTlsCertificate::pkcs12('/path/certificate.p12', 'passphrase'),
    environment: Environment::Live,   // il default è la sandbox
));

// Saldo di oggi, oppure di una data passata
$balance = $client->accounts()->balance();
echo $balance->availableBalance;

// Movimenti di fine giornata di ieri, con tutte le pagine già raccolte
$list = $client->accounts()->transactions(new DateTimeImmutable('yesterday'));

if (! $list->isComplete()) {
    // la banca ha dichiarato più movimenti di quelli ricevuti
}

foreach ($list->accounted as $transaction) {
    echo $transaction->uniqueId, ' ', $transaction->amount, ' ', $transaction->valueDate?->format('d/m/Y');
}

// Movimenti di oggi, contabilizzati e non ancora contabilizzati
$today = $client->accounts()->todayTransactions();

// Bonifico istantaneo: simulazione, controllo VoP, esecuzione
$payment = new InstantPayment(
    creditorName: 'Mario Rossi',
    creditorIban: 'IT…',
    amount: 150.25,
    paymentInformation: 'Fattura 1/2026',
    debtorName: 'Example Srl',
);

$simulation = $client->sctInstant()->simulate($payment);
$execution = $client->sctInstant()->execute($payment, $simulation);   // con VoP non pieno: VopConfirmationRequiredException
```

Verifiche IBAN, utili prima di un bonifico:

```php
$client->ibanTools()->verify('IT…', vatNumber: '01234567890')->isAllowed();     // esiste ed è dell'intestatario (CF o P.IVA obbligatori)
$client->ibanTools()->formalCheck('IT…')->isValid();                            // scomposizione, solo IBAN Intesa Sanpaolo
$client->sctInstant()->reachability('IT…')->isReachable();                      // istantaneo possibile?
```

Il bonifico ordinario funziona allo stesso modo con `SctPayment` e `$client->sct()`, che offre anche lo stato per CRO, ID pagamento, end-to-end ID o simulazione, e la revoca. I flussi completi, con esiti, resubmit e simulazioni salvate, sono in [docs/payment-flows.md](docs/payment-flows.md).

- Le risposte sono oggetti immutabili con proprietà pubbliche.
- Le date sono `DateTimeImmutable` ([docs/dates-and-formats.md](docs/dates-and-formats.md)).
- Gli errori sono eccezioni che implementano `Shellrent\OpenBanking\Exception\OpenBankingException` ([docs/architecture.md](docs/architecture.md)).

## Sviluppo

```bash
composer install
composer ci      # validate, formattazione (Pint), analisi statica (PHPStan), test (PHPUnit)
```

Le regole del progetto (convenzioni, quirk delle API, uso delle spec) sono in [AGENTS.md](AGENTS.md) e nella cartella [docs](docs/README.md). Per contribuire leggi [CONTRIBUTING.md](CONTRIBUTING.md).

## Sicurezza

Per segnalare una vulnerabilità **non aprire una issue pubblica**: segui le indicazioni di [SECURITY.md](SECURITY.md).

## Note legali

Questo progetto non è affiliato a Intesa Sanpaolo né approvato da Intesa Sanpaolo. I nomi e i marchi citati appartengono ai rispettivi proprietari.

## Sponsor

<!--special start-->
<p>
  <a href="https://www.shellrent.com" target="_blank">
      <img height="200" src="./docs/assets/shellrent.jpg">
  </a>
</p>
<!--special end-->

## Credits

- [Tobia Zanarella](https://github.com/shellrent)
- [Armando Caprio](https://github.com/shellrent)
- [Shellrent Srl](https://www.shellrent.com)
- [Tutti i contributori](../../contributors)

## Licenza

MIT. Vedi [LICENSE.md](LICENSE.md).
