# Flussi di pagamento

Come si esegue un bonifico con l'SDK, e cosa fare in ogni esito. I comportamenti delle API citati (Q-…) sono in [api-quirks.md](api-quirks.md).

> Stato: SCT Instant (T5) e SCT ordinario (T6) completi.

## Regole che valgono per ogni bonifico

1. **Prima si simula, poi si esegue.** La simulazione verifica fattibilità e beneficiario e restituisce un `simulationId` valido **5 minuti** (Q-33). L'esecuzione richiede la simulazione.
2. **Eseguire equivale ad accettare l'esito della Verification of Payee (VoP)** (Q-37). L'SDK esegue in automatico solo con un match pieno. In ogni altro caso, compreso un esito assente o sconosciuto, `execute()` lancia `VopConfirmationRequiredException` finché non si passa `acceptVopResult: true` (D-36).
3. **Nessun retry automatico.** Dopo una `TransportException` su un'esecuzione l'esito è **sconosciuto**: il bonifico potrebbe essere partito. Prima di riprovare si controlla lo stato o la lista dei pagamenti.
4. **La simulazione deve corrispondere al pagamento.** `execute()` rifiuta una simulazione non accettata, o fatta per un altro creditore o un altro importo (`InvalidRequestException`).
5. **Importi**: euro, positivi, al massimo 2 decimali. L'errore di calcolo dei float viene assorbito (`0.1 + 0.2` diventa `0.3`), un importo come `10.567` viene rifiutato (D-38).
6. **Testi**: l'SDK controlla solo i campi obbligatori e le lunghezze SEPA (nomi 70 caratteri, causale 140, `endToEndId` 35) e invia i caratteri così come sono. Per restare nel set ammesso dalla banca c'è l'helper opzionale `SepaText::sanitize()`. Attenzione: cambiare il nome del creditore può cambiare l'esito VoP (D-39).

## SCT Instant

### 1. Simulazione

```php
use Shellrent\OpenBanking\Request\InstantPayment;

$payment = new InstantPayment(
    creditorName: 'Mario Rossi',
    creditorIban: 'IT…',
    amount: 150.25,
    paymentInformation: 'Fattura 1/2026',
    debtorName: 'Example Srl',      // intestatario del conto
    // debtorIban: null              → IBAN della configurazione
    // endToEndId, siaCode           → opzionali
);

$simulation = $client->sctInstant()->simulate($payment);

$simulation->isAccepted();          // esito ACCP/ACSC e simulationId presente
$simulation->vop->result;           // VopMatchResult: Match, CloseMatch, NoMatch, NotApplicable, …
$simulation->vop->matchedName;      // nome suggerito in caso di close match
```

### 2. Esecuzione

```php
use Shellrent\OpenBanking\Exception\VopConfirmationRequiredException;

try {
    $execution = $client->sctInstant()->execute($payment, $simulation);
} catch (VopConfirmationRequiredException $e) {
    // Esito VoP non pieno: mostrarlo a un operatore ($e->vop) e, se confermato:
    $execution = $client->sctInstant()->execute($payment, $simulation, acceptVopResult: true);
}
```

### Simulazione ed esecuzione in processi diversi

Se la simulazione e l'esecuzione avvengono in momenti diversi (per esempio due passi di un daemon), si salva la simulazione e la si ricarica entro 5 minuti. Il controllo VoP resta attivo anche sulla simulazione ricaricata:

```php
$stored = json_encode($simulation->toArray());            // es. su database
$simulation = InstantSimulation::fromArray(json_decode($stored, true));
```

### 3. Esiti dell'esecuzione

| `$execution->paymentStatus` | Codice | Significato | Cosa fare |
|---|---|---|---|
| `PaymentStatus::Executed` | `ACSC` | bonifico eseguito | fine |
| `PaymentStatus::Pending` | `PNDG` (anche `PDNG`) | esecuzione sospesa per controlli | `resubmit()` se `canBeResubmitted()` |
| `PaymentStatus::ToVerify` | `VRFY` | esito non disponibile | dopo **10 minuti** cercarlo in `payments()`: se c'è è eseguito, altrimenti no |
| `PaymentStatus::Rejected` | `RJCT` | rifiutato dalla banca | correggere e ripartire dalla simulazione |
| `null` | altro | valore sconosciuto | vedere `rawPaymentStatus` |

`PaymentStatus::isAccepted()` dice se la banca ha preso in carico l'ordine, che quindi non va pagato di nuovo. `isFinal()` dice se lo stato non cambierà più.

### 4. Resubmit di un pagamento sospeso

```php
if ($execution->canBeResubmitted()) {
    $execution = $client->sctInstant()->resubmit($payment, $simulation->simulationId, $execution->resubmitId);
}
```

L'SDK invia sempre insieme `resubmit: true` e `resubmitId` (Q-36). La 2.x inviava `resubmitId` solo se si chiamava anche `setResubmit(true)`.

### 5. Stato e lista

```php
$details = $client->sctInstant()->status($execution->orderId);   // InstantStatus: Completed, Canceled, Rejected, Returned
$list    = $client->sctInstant()->payments(new DateTimeImmutable('-7 days'));
$list->isComplete();
```

La lista raccoglie tutte le pagine (100 pagamenti per pagina) e si ferma su una pagina vuota. Senza data di inizio usa il mese precedente, come la 2.x.

### Live e sandbox

Con `endToEndId` o `siaCode` vuoti, in sandbox l'SDK li invia come stringhe vuote e in Live li omette (Q-34). L'`endToEndId` `NOTPROVIDED` restituito dalla banca diventa `null` (Q-35).

## SCT (bonifico ordinario)

Valgono le stesse regole: simulazione, controllo VoP con blocco in `execute()`, nessun retry, simulazione salvabile con `toArray()`/`fromArray()`.

### Simulazione ed esecuzione

```php
use Shellrent\OpenBanking\Request\SctPayment;

$payment = new SctPayment(
    creditorName: 'Fornitore Esempio Srl',
    creditorIban: 'IT…',
    amount: 1250.00,
    paymentInformation: 'Fattura 2/2026',
    debtorName: 'Example Srl',
    requestedExecutionDate: new DateTimeImmutable('today'),   // opzionale: senza data decide la banca
    categoryPurpose: 'SUPP',                                 // default CASH, come la 2.x
    // currency: 'EUR'                                       → default
);

$simulation = $client->sct()->simulate($payment);
$simulation->fees;              // commissioni
$simulation->executionDate;     // date previste di esecuzione, valuta e regolamento
$simulation->vop;               // esito VoP

$execution = $client->sct()->execute($payment, $simulation);   // VoP non pieno: VopConfirmationRequiredException
```

### Esiti dell'esecuzione

| `$execution->paymentStatus` | Codice | Significato | Cosa fare |
|---|---|---|---|
| `PaymentStatus::InProgress` | `ACSP` | fondi bloccati, esecuzione in corso | seguire con `status()` |
| `PaymentStatus::Accepted` | `ACTC` | ordine inserito, fondi non ancora bloccati (data futura, oltre il cut-off, giorno non lavorativo) | seguire con `status()`; è revocabile fino a `revokeDate` |
| `PaymentStatus::ToVerify` | `VRFY` | esito non disponibile | ricontrollare più tardi con `status()` o `payments()` |
| `PaymentStatus::Rejected` | `RJCT` | rifiutato dalla banca | correggere e ripartire dalla simulazione |

`ACSP` e `ACTC` sono ordini **presi in carico** (`isAccepted()`): non vanno pagati di nuovo. Gli stati finali (`isFinal()`) arrivano da `status()` e `payments()`: `ACSC` eseguito, `CANC` cancellato, `RETU` richiamato, `RJCT` rifiutato.

### Stato, lista, revoca

```php
// Per CRO o ID pagamento; per simulationId o endToEndId se l'esito di un'esecuzione è sconosciuto
$details = $client->sct()->status(customerCro: $execution->customerCro);
$details = $client->sct()->status(simulationId: $simulation->simulationId);

$list = $client->sct()->payments(new DateTimeImmutable('-7 days'));   // tutte le pagine, isComplete()

$revocation = $client->sct()->revoke(customerCro: $execution->customerCro);
$revocation->isRevoked();       // solo entro il cut-off della banca (Q-40)
```

### Dopo un errore di rete sull'esecuzione

Dopo una `TransportException` il bonifico potrebbe essere partito. Prima di simulare e riprovare si cerca il pagamento con `status(simulationId: …)` (o `endToEndId`), oppure nella lista.
