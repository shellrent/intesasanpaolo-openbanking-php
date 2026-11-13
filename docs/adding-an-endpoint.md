# Aggiungere una nuova API

Ricetta per integrare un'API SmartHub nella libreria. L'esempio guida è la verifica IBAN (`IbanToolsApi::verify()`, endpoint `chargeabilityMulti`), aggiunta in T7.

## 1. Studiare la spec e la documentazione

1. Trova il file di spec giusto in [specs-reference.md](specs-reference.md): file canonico Live e sandbox, doppioni da ignorare, anomalie note. Le spec sono locali (`../openbanking-openapi`) e **non vanno mai committate**.
2. Leggi endpoint, parametri, schema della risposta 200, header di risposta ed esempi, per esempio con `jq` (vedi `specs-reference.md`).
3. Confronta con la pagina SmartHub dell'API e con le FAQ: spesso i nomi dei campi differiscono. Nell'esempio, la spec dice `allowed`, mentre la definizione interna e la pagina SmartHub usano `isAllowed` con valore stringa `"true"`.
4. Annota ogni differenza o comportamento particolare in [api-quirks.md](api-quirks.md), come nuova voce Q-… con la fonte (`SPEC`, `WEB`, `FAQ`, …). Esempi da T7: Q-19 (parti dell'IBAN come stringhe), Q-20 (varianti dell'esito), Q-47 (base path).

## 2. Scegliere dove va il metodo

- Un'area esistente se l'API ne fa parte (la raggiungibilità Instant è `sctInstant()->reachability()`), altrimenti un nuovo oggetto `Api\…Api` con il suo accessor nella facade `IntesaSanPaoloClient` (es. `ibanTools()`).
- Il costruttore dell'API è `@internal` e riceve `Transport`, più `ClientConfig` o `Paginator` se servono.

## 3. Richiesta

- **Parametri semplici**: si validano nel metodo con `Request\Validator`, che lancia `InvalidRequestException` prima di qualsiasi chiamata. Nell'esempio: `Validator::iban()` e `Validator::taxCode()`.
- **Body strutturato** (pagamenti): una classe `final readonly` in `Request\` che valida e normalizza nel costruttore. Il body JSON lo costruisce il metodo dell'API, applicando le differenze tra Live e sandbox.
- **Valori in uscita**: date con `DateParser::format()` e il formato giusto (`DateFormat`), IBAN normalizzati.

## 4. Risposta

Una classe `final readonly` in `Response\<Area>\` con proprietà pubbliche che portano i nomi dell'API in camelCase:

```php
final readonly class IbanVerification
{
    public function __construct(
        public ?bool $allowed,
        public ?string $additionalInfo,
        public ?string $ibanType,
        public ?string $transactionId = null,
    ) {}

    public function isAllowed(): bool
    {
        return $this->allowed === true;
    }

    /** @internal */
    public static function fromResponse(ApiResponse $response): self
    {
        $payload = $response->requirePayload();

        return new self(
            allowed: $payload->bool('allowed', 'isAllowed', 'IsAllowed'),   // tutte le varianti note
            additionalInfo: $payload->string('additionalInfo'),
            ibanType: $payload->string('ibanType'),
            transactionId: $response->transactionId,
        );
    }
}
```

Regole:
- **Si legge solo tramite `Payload`**, che gestisce i nomi alternativi, i tipi tolleranti e gli errori con il percorso del campo.
- **Le date sempre con un formato esplicito**: `$payload->date(DateFormat::…, 'campo')`, mai `new DateTime()`.
- **Gli stati come enum** (con il trait `FromApiValue` o un `fromApi()` dedicato), affiancati dal valore originale `raw…`.
- **Nullable di default**: è obbligatorio (`require…()`) solo ciò senza cui la risposta non ha senso, come l'importo di un movimento.
- **`transactionId` nelle risposte singole**, `transactionIds` nelle liste paginate.
- **Metodi di comodo** per le domande ricorrenti: `isAllowed()`, `isReachable()`, `isComplete()`.
- Per le liste paginate si usa `Paginator::pages()` con una closure che estrae il link successivo; i link della sandbox li corregge `SandboxLinkRewriter`.

## 5. Test

- Le fixture vanno in `tests/Fixtures/<Area>/` con dati fittizi ma struttura e formati reali (IBAN segnaposto `IT00X0000000000000000000001`, codici fiscali come `AAAAAA00A00A000A`).
- Il test dell'API usa `MockHttp`. Verifica la URL e la query inviate, i campi letti e ogni quirk, citando la voce nel PHPDoc (`Q-20: …`), e anche gli errori di validazione e gli esiti negativi.
- Il riferimento è `tests/Unit/Api/IbanToolsApiTest.php`.

## 6. Documentazione e chiusura

- [api-coverage.md](api-coverage.md): riga dell'endpoint con il metodo 3.0.
- [api-quirks.md](api-quirks.md): le nuove voci (passo 1).
- `README.md`: tabella delle funzionalità e, se utile, un esempio.
- `UPGRADE-3.0.md` se sostituisce un metodo 2.x; `CHANGELOG.md`, sezione *Unreleased*.
- `composer ci` verde. In locale, con l'estensione PECL `psr`, usa `php8.4 $(command -v composer) ci` ([testing.md](testing.md)).
