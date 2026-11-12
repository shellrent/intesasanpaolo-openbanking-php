# Quirk e comportamenti reali delle API

Questo documento raccoglie i comportamenti delle API Intesa Sanpaolo che non risultano ovvi dalle spec o che le contraddicono, e le scelte che ne derivano nella libreria.

**Regola:** un quirk documentato qui è intenzionale. Non va "corretto" per allinearlo alla spec senza un'evidenza nuova. Se cambi un comportamento, aggiorna la voce con la fonte.

## Fonti

| Sigla | Significato |
|---|---|
| `SPEC` | spec attuale (file canonico in [specs-reference.md](specs-reference.md)) |
| `SPEC-OLD` | spec di una generazione precedente (`old/`) |
| `WEB` | pagine di documentazione SmartHub |
| `FAQ` | FAQ SmartHub |
| `PROD` | evidenza dall'uso reale in produzione: codice 2.x e relativo commit |
| `CONSUMER` | evidenza dal gestionale che usa la libreria in produzione |
| `LIVE` | chiamata reale in Live, di sola lettura, fatta con la 3.0 (verifiche di T8, 12/11/2026) |
| `SANDBOX` | chiamata reale in sandbox, fatta con la 3.0 (verifiche di T8, 12/11/2026) |

## Trasporto e autenticazione

### Q-01 Base URL e mutua autenticazione (mTLS)
La base URL è `https://external-api.intesasanpaolo.com` + `/twa` + `/{live|sandbox}` + `/v1`. Tutte le spec attuali usano `/twa/`, cioè la mutua autenticazione con certificato; le spec del 2020 non avevano `/twa/`.
- 2.x: usa `/twa/` solo se è configurato un certificato.
- 3.0: il certificato è obbligatorio e la base URL è sempre `/twa/` (D-23).
- Fonti: `SPEC`, `SPEC-OLD`.

### Q-02 Token OAuth2
`POST https://external-api.intesasanpaolo.com/auth/oauth/v2/token`, senza `/twa` né ambiente nel percorso.
- Header `Authorization: Basic base64(clientId:clientSecret)`; body form `grant_type=client_credentials&scope=oob`.
- Dalla 2.1.0 anche la richiesta del token usa il certificato mTLS (commit `5d53728`).
- Risposta: `access_token`, `token_type` (la 2.x verifica che sia `Bearer`), `expires_in` in secondi.
- Fonti: `SPEC` (securityDefinitions), `PROD`.

### Q-03 Header `apikey`
La 2.x invia `apikey: <clientId>` in ogni chiamata, anche se le spec non lo prevedono. Funziona in produzione: va mantenuto.
- Fonte: `PROD`.

### Q-04 `Content-Type: application/json` sulle GET SCT
Le GET di stato (`/payments/bonsct/{iban}/history`) e lista (`/payments/bonsct/{iban}/list`) dei bonifici SCT vengono inviate con `Content-Type: application/json`. La spec non lo richiede, ma la 2.x lo fa da quando ha introdotto le chiamate SCT (2.0.0): va mantenuto.
- 3.0: `SctApi` invia l'header sulle GET di stato e lista, su ogni pagina, ma non sulla revoca, come la 2.x.
- Fonte: `PROD`.

### Q-05 Struttura della risposta ed errori
Ogni risposta ha la forma `{ "success": bool, "errors": ..., "payload": ... }`. Con `success` falso il `payload` è `null`.
- Errori (spec attuali): `errors[]` con `type`, `code`, `description`.
- La 2.x gestisce anche un formato alternativo con `title` e `detail.reason`.
- Le spec `old/` elencano i valori di `type`: `INPUT_EXCEPTION`, `EMPTY_INPUT_EXCEPTION`, `WRONG_INPUT_EXCEPTION`, `NULL_INPUT_EXCEPTION`, `APPLICATION_EXCEPTION`, `TECHINCAL_EXCEPTION` (sic), `UNDEFINED_EXCEPTION`, `DEV_EXCEPTION`, `NOT_FOUND_EXCEPTION`.
- Codici HTTP documentati: 400, 401, 403, 404, 405, 500 e 502 (sulle API SCT).
- Gli errori del gateway usano il formato alternativo, senza `success` né `payload`. Per esempio, un'API non abilitata sull'app OAuth risponde HTTP 403 con `{"errors": [{"status": "403", "title": "Forbidden", "detail": {"reason": "You are not allowed to access the requested resource"}, "links": []}]}`.
- In Live alcune risposte di successo non hanno la chiave `errors` (liste SCT e SCT Instant, stato SCT), altre la valorizzano con `[]` (raggiungibilità).
- Una revoca SCT non riuscita arriva con HTTP 200 e `success: false`, ma senza `errors`: vedi Q-31.
- Fonti: `SPEC`, `SPEC-OLD`, `PROD`, `LIVE`, `SANDBOX`.

### Q-06 Header `X-ISP-Transaction-ID`
Secondo la spec quasi tutte le risposte, anche di errore, contengono l'header `X-ISP-Transaction-ID`, l'identificativo della chiamata da indicare al supporto ISP. La 2.x lo scarta.
- Nelle risposte reali, sia Live sia sandbox, l'header non c'è: le eccezioni e i DTO hanno quindi `transactionId` a `null`. La 3.0 continua a leggerlo, nel caso compaia.
- Fonti: `SPEC`, `LIVE`, `SANDBOX`.

### Q-07 Header della vecchia spec del saldo
La spec mTLS del saldo del 2021 (variante "Business accounts") richiedeva gli header `X-ISP-Transaction-ID` e `ispwebservicesheader.businessinfo.customerid`. Le spec attuali non li prevedono, la 2.x non li invia e funziona: vanno ignorati.
- Fonti: `SPEC-OLD`, `PROD`.

### Q-08 Certificati mTLS
- Il certificato è unico per cliente e vale sia per Live sia per sandbox.
- Dura 1 anno; se ne possono avere al massimo 2 attivi, quindi il nuovo va generato e installato prima che scada il vecchio.
- Va configurato il trust dell'intera catena della CA, non del solo certificato.
- Formati supportati da Guzzle: PEM con chiave privata separata, oppure PKCS#12 (da Guzzle 7.3).
- Il file `.p12` scaricato da SmartHub usa una cifratura che OpenSSL 3 non legge: vedi Q-48.
- Fonti: `FAQ`, `PROD`.

## Date

### Q-09 Trappola di PHP sulle date con lo slash
`new DateTime('02/01/2022')` restituisce **il 1° febbraio 2022** (formato americano m/d/Y) senza lanciare eccezioni; `new DateTime('13/01/2022')` invece lancia un'eccezione.
- Il fallback della 2.x ("prova `new DateTime`, poi `d/m/Y`") sbaglia quindi per tutti i giorni da 1 a 12.
- `Carbon::parse()` si comporta allo stesso modo.
- `DateTime::createFromFormat('d/m/Y', ...)` senza `!` aggiunge l'ora corrente.
- Regola: ogni campo data si legge con il suo formato esplicito, secondo la tabella seguente.
- Fonte: verificato con PHP 8.3 e Carbon 2.73.

### Q-10 Formati delle date per campo

| API | Direzione | Campo | Formato | Esempio |
|---|---|---|---|---|
| Saldo | richiesta | `date` | `Ymd` | `20180131` |
| Saldo | risposta | `inquiryDate` | `Ymd` | `20180131` |
| Saldo | risposta | `executionDate` | ISO 8601 con offset; in Live senza millisecondi, in sandbox con `:` prima dei millisecondi (Q-49) | `2018-05-04T09:52:10.289+0200` |
| Movimenti | richiesta | `date` (da ieri fino a 18 mesi prima) | `Ymd` | `20180201` |
| Movimenti | risposta | `inquiryDate` | `Ymd` | `20180131` |
| Movimenti | risposta | `executionDate` | ISO 8601 | `2017-12-31T23:59:60Z` |
| Movimenti | risposta | `date`, `accountingDate`, `valueDate` | `Ymd` (`valueDate` può essere vuoto o `"0"`, vedi Q-23) | `20171231` |
| Liste pagamenti | richiesta | `fromDate`, `toDate` | `Ymd` | `20171231` |
| SCT Instant simulazione/esecuzione | risposta | `date` | ISO 8601 UTC | `2017-12-31T23:59:60Z` |
| SCT Instant lista | risposta | `inquiryFromDate`, `inquiryToDate` | `Ymd` per la spec; la 2.x accetta anche `d/m/Y` | `20171231` |
| SCT Instant lista | risposta | `payments[].valueDate` | `d/m/Y` | `13/12/2018` |
| SCT | richiesta | `requestedExecutionDate` | `d/m/Y` | `13/01/2022` |
| SCT simulazione/esecuzione/stato | risposta | `executionDate`, `valueDate`, `settlementDate`, `revokeDate` | `d/m/Y` | `02/01/2022` |
| SCT esecuzione | risposta | `revokeTime` | `H:i:s` | `17:30:00` |
| SCT lista | risposta | `inquiryFromDate`, `inquiryToDate` | `Ymd` per la spec; la 2.x accetta anche `d/m/Y` | `20171231` |
| SCT lista | risposta | `executionInquiry` (non `executionDate`) | ISO 8601 | `2017-12-31T23:59:60Z` |
| SCT lista | risposta | `paymentsResults[].executionDate`, `valueDate`, `settlementDate` | `d/m/Y` | `02/01/2022` |

Gli esempi della spec usano anche `23:59:60` (secondo intercalare), che PHP converte nel minuto successivo.

Nelle risposte reali gli `executionDate` hanno l'offset senza i due punti (`+0000` in Live, `+0100` in sandbox). In sandbox `payments[].valueDate` della lista SCT Instant è in formato `Ymd` invece che `d/m/Y`: la 3.0 accetta entrambi (fonti: `LIVE`, `SANDBOX`).

### Q-11 Fuso orario
Le date senza orario sono date contabili italiane: si interpretano a mezzanotte nel fuso `Europe/Rome`. Le date ISO 8601 mantengono l'offset indicato. Vedi la decisione D-07 in [decisions.md](decisions.md).

## Nomi dei campi

### Q-12 IBAN: `debtorIBAN` o `debtorIban`
SCT Instant usa `debtorIBAN`/`creditorIBAN`; SCT ordinario usa `debtorIban`/`creditorIban`, sia nelle richieste sia nelle risposte. Nelle risposte vanno lette entrambe le varianti.
- Fonti: `SPEC`, `PROD` (commit `ec13495`).

### Q-13 `orderId` o `order-id`, `paymentId` o `payment-id`
`order-id` compare nella spec 2020 dello stato SCT Instant. La 2.x legge entrambe le varianti di tutti e due i campi.
- Fonti: `SPEC-OLD`, `PROD`.

### Q-14 CRO: `customerCro` o `customerCRO`
L'esecuzione SCT Instant restituisce `customerCro`; le API SCT usano `customerCRO`, ma la pagina SmartHub SCT le usa entrambe.
- Fonti: `SPEC`, `WEB`.

### Q-15 Commissioni SCT: `fees` o `commissioni`
La spec SCT del 2023 e la pagina SmartHub usano `commissioni`, la spec attuale `fees`. Vanno lette entrambe; la 2.x legge solo `commissioni`.
- 3.0: entrambi i nomi sono letti nella proprietà `fees`.
- Fonti: `SPEC-OLD`, `SPEC`, `WEB`.

### Q-16 `settlementDate` (non `settlemenDate`)
Il nome corretto è `settlementDate`. `PaymentExecuted` della 2.x legge anche `settlemenDate`, un refuso senza fonte nelle spec.
- 3.0: `settlemenDate` non viene più letto; la data è in `settlementDate`.
- Fonti: `SPEC`, `WEB`.

### Q-17 Lista SCT: `executionInquiry`
Nella lista SCT la data di estrazione si chiama `executionInquiry`, non `executionDate`.
- 3.0: `executionInquiry` è esposta come `SctPaymentList::$executionDate`.
- Fonte: `SPEC`.

### Q-18 Raggiungibilità SCT Instant
I campi della risposta sono `isReachable`, `IBAN_Status` e `BIC`, con le maiuscole.
- `IBAN_Status` è un testo che contiene l'IBAN completo, per esempio `Iban IT… : address Bank is reachable for SCT Instant`: va trattato come dato personale nei log.
- In Live tutti gli IBAN provati, italiani ed esteri, hanno restituito `isReachable: true` con il BIC della banca.
- 3.0: `SctInstantApi::reachability()` restituisce `InstantReachability` (`reachable`, `status`, `bic`).
- Fonti: `SPEC`, `LIVE`, `SANDBOX`.

### Q-19 Verifica sintattica IBAN: campi "numerici"
`checkDigits`, `bankCode`, `bankAgency` e `account` sono dichiarati `number`, ma sono stringhe con zeri iniziali o lettere (es. `"03069"`, `"XTESTCASEX1"`): vanno trattati come stringhe.
- 3.0: `IbanFormalCheck` espone queste parti come stringhe. Se l'API le inviasse come numeri, gli zeri iniziali andrebbero persi: il `bban` resta il riferimento completo.
- In sandbox arrivano come stringhe (`"checkDigits": "04"`, `"bankCode": "03069"`).
- Fonti: `SPEC`, `WEB`, `SANDBOX`.

### Q-20 `chargeabilityMulti`: esito
La spec restituisce `allowed` e `additionalInfo`. La pagina SmartHub mostra invece `IsAllowed`/`isAllowed`, con valore stringa `"true"`: l'esito va letto in modo tollerante.
- `additionalInfo` vale `AC15` se il conto è stato trasferito: esito OK se è rimasto nel gruppo ISP con nuove coordinate, KO se è passato a un'altra banca.
- In sandbox alcuni esiti negativi arrivano con HTTP 200 e `success: false`, senza `errors`, ma con l'esito nel `payload`: per esempio `{"isAllowed": false, "additionalInfo": "… vatNumber is mandatory for companies accounts"}` oppure `"vatNumber is not associated with the input iban"`. La spec li elenca tra i casi di test con risposta 200. Gli esiti positivi e gli altri negativi arrivano con `success: true`.
- Senza codice fiscale né partita IVA la sandbox risponde `WI05` (Q-51).
- 3.0: `IbanToolsApi::verify()` legge `allowed`, `isAllowed` e `IsAllowed`, anche come stringa `"true"`, più `ibanType`. `IbanVerification::isTransferred()` riconosce `AC15`. Una risposta con `success: false`, un `payload` e nessun errore è un esito negativo, non un errore. Con il solo IBAN è la verifica di esistenza (D-51).
- Fonti: `SPEC`, `WEB`, `SANDBOX`.

## Campi assenti o nulli

### Q-21 Saldo
Tutti i campi del saldo possono essere `null` o mancare.
- Fonte: `PROD` (commit `7a4d7ac`, `d07ec14`, `f46b6d4`).

### Q-22 Dati di debitore e creditore
IBAN e nomi di debitore e creditore possono mancare o essere `null` nelle risposte.
- Fonte: `PROD` (commit `1efb774`).

### Q-23 Movimenti
- I movimenti non contabilizzati (solo intraday) non hanno `uniqueId`, `date`, `accountingDate` né `additionalInfo`; il loro `status` è `unaccounted`.
- `valueDate` può essere vuoto o `"0"` (commit `6833c0b`); in quel caso la 2.x usa la data corrente, la 3.0 restituisce `null`.
- Lo `status` dei movimenti contabilizzati vale `accounted`, `reversed` o `canceled` per la spec; in Live arriva in maiuscolo (`ACCOUNTED`). La conversione in enum ignora le maiuscole.
- In Live `type` ha spazi finali di riempimento (per esempio `"COMM. BEU INTER.  "`): la 3.0 restituisce il valore così com'è.
- Fonti: `SPEC`, `WEB`, `PROD`, `LIVE`.

### Q-24 `remittanceInformation`
Nelle liste dei pagamenti è presente solo in caso di remittance, altrimenti è `null`.
- Fonte: `SPEC`.

## Stati

### Q-25 Esito della simulazione SCT Instant (`validationStatus`)
- Live, spec attuale: `ACSC` / `RJCT`.
- Sandbox: `ACCP` / `RJCT`.
- Spec 2021: `ACCP accepted` / `RJCT rejected`.
- Il gestionale gestisce anche `ACSC acquired`.

Il valore può quindi contenere una descrizione dopo il codice: il codice è il primo token.
- 3.0: `ValidationStatus::fromApi()` usa il primo token e tratta `ACSC` come `Accepted` (D-11).
- La sandbox risponde `ACCP` e, sulle simulazioni rifiutate, `simulationId: ""`.
- Fonti: `SPEC`, `SPEC-OLD`, `CONSUMER`, `SANDBOX`.

### Q-26 Esito della simulazione SCT (`validationStatus`)
`ACCP` / `RJCT`.
- Fonte: `SPEC`.

### Q-27 Stato dell'esecuzione SCT Instant (`paymentStatus`)
`ACSC`, `PNDG`, `RJCT`, `VRFY`; `VRFY` è stato aggiunto dopo la spec 2021. La FAQ scrive `PDNG` invece di `PNDG`: vanno accettati entrambi.
- Il caso di test della sandbox per l'esecuzione sospesa restituisce proprio `PDNG` (`transactionStatusDescription: "PDNG-Prtry:XX000"`), insieme al `resubmitId`.
- La risposta `VRFY` della sandbox ha quasi tutti i campi valorizzati con la stringa `"null"`, compresi `orderId`, `resubmitId` e `date`: vedi Q-50.
- 3.0: `PaymentStatus::Pending` accetta anche `PDNG`.
- Fonti: `SPEC`, `SPEC-OLD`, `FAQ`, `SANDBOX`.

### Q-28 Stato dell'esecuzione SCT (`paymentStatus`)
`ACSP`, `RJCT`, `VRFY`, `ACTC`; `ACTC` è stato aggiunto dopo la spec 2023.
- 3.0: `PaymentStatus`. `ACTC` vale come preso in carico (`isAccepted()`) ma non finale.
- I casi di test della sandbox restituiscono `ACSP`, `ACTC` (data di esecuzione futura) e `VRFY`.
- Fonti: `SPEC`, `SPEC-OLD`, `SANDBOX`.

### Q-29 Stato e lista SCT (`paymentStatus`)
`ACSP`, `RJCT`, `CANC`, `ACSC`, `RETU`, `ACTC`.
- 3.0: `PaymentStatus`; `status()` accetta tutti e quattro gli identificativi documentati.
- In Live la ricerca per CRO, per ID pagamento e per `endToEndId` restituisce lo stesso bonifico. I bonifici eseguiti nei giorni precedenti risultano `ACSC`, anche quando il gestionale li ha registrati come `ACSP` al momento dell'esecuzione: lo stato va riletto dopo l'esecuzione.
- La risposta Live dello stato non contiene `creditorNation`, `creditorAddress` né `ultimateCreditorName`.
- Fonti: `SPEC`, `LIVE`, `SANDBOX`.

### Q-30 Stato e lista SCT Instant (`status`)
`Completed`, `Rejected`, `Returned`, più `Cancelled` nello stato Live oppure `Canceled` nella lista e in sandbox.
- Il caso di test "Canceled" dello stato in sandbox restituisce in realtà `Cancelled`: la 3.0 accetta entrambe le grafie.
- Fonti: `SPEC`, `SANDBOX`.

### Q-31 Revoca SCT (`status`)
`OK` / `KO`.
- In sandbox una revoca non riuscita arriva con HTTP 200, `success: false`, nessun `errors` e `payload.status` = `"KO - Generic error"`.
- 3.0: `SctRevocation::$revoked`, che vale true, false oppure null se il valore è sconosciuto; il codice è la prima parola di `status`. Una risposta con `success: false`, un `payload` e nessun errore è un esito negativo, non un errore: `revoke()` restituisce `revoked: false`. Con degli `errors` resta un'`ApiException`.
- Fonti: `SPEC`, `SANDBOX`.

### Q-32 Significato dei codici di stato

| Codice | Significato |
|---|---|
| `ACCP` | simulazione eseguita con successo |
| `ACSC` | bonifico eseguito |
| `ACSP` | (SCT) fondi bloccati, esecuzione in corso |
| `ACTC` | (SCT) ordine inserito, fondi non ancora bloccati: data futura, oltre il cut-off o giorno non lavorativo |
| `PNDG` / `PDNG` | (SCT Instant) esecuzione sospesa per controlli |
| `RJCT` | ordine rifiutato dalla banca |
| `CANC` | (SCT) ordine cancellato dal cliente |
| `RETU` | (SCT) bonifico richiamato con successo |
| `VRFY` | stato non recuperabile, da ricontrollare più tardi. SCT Instant: dopo 10 minuti si chiama la lista pagamenti; se il bonifico c'è è stato eseguito, se manca non è andato a buon fine |

- Fonte: `FAQ`.

## Flussi di pagamento

### Q-33 `simulationId`
Vale 5 minuti, ha al massimo 49 caratteri ed è obbligatorio per l'esecuzione. Per SCT Instant è diventato obbligatorio dopo la spec 2021.
- Fonti: `SPEC`, `SPEC-OLD`.

### Q-34 `endToEndId` e `siaCode`: Live diverso da sandbox
Riguarda `endToEndId` e `siaCode` per SCT Instant, e il solo `endToEndId` per SCT.
- In sandbox i campi devono essere presenti, anche vuoti.
- In Live, se vuoti, vanno omessi.
- 3.0: lo stesso comportamento vale per simulazione ed esecuzione. Nell'esecuzione in sandbox la 2.x inviava `null` per un `endToEndId` mancante.
- Quasi tutti i casi di test della sandbox usano come `endToEndId` un UUID di 36 caratteri, oltre il limite SEPA di 35. La 3.0 mantiene il limite (D-56), quindi quei casi non si possono eseguire con l'API pubblica dell'SDK.
- Fonti: `PROD` (commit `2bcbaea`, versione 1.1.2), `SANDBOX`.

### Q-35 `endToEndId` non fornito
Quando non viene fornito un `endToEndId`, la banca restituisce `NOTPROVIDED` (oppure `NOT PROVIDED` negli esempi). Il gestionale lo tratta come valore assente.
- 3.0: `NOTPROVIDED`, con o senza spazi, diventa `null` sia nelle risposte sia nelle richieste.
- Fonti: `WEB`, `CONSUMER`.

### Q-36 Resubmit (SCT Instant)
Se l'esecuzione resta in `PNDG`, la risposta contiene un `resubmitId`; per riprovare si invia `resubmit: true` insieme al `resubmitId`. La 2.x invia questi campi solo se si è chiamato `setResubmit(true)`.
- 3.0: `SctInstantApi::resubmit()` invia sempre `resubmit: true` insieme a `resubmitId`.
- Fonte: `SPEC`.

### Q-37 Verification of Payee (VoP)
Le simulazioni (SCT e SCT Instant, sia JSON sia ISO) restituiscono l'esito VoP in tre header di risposta:
- `vopMatchResult`: esito del confronto tra IBAN e nome del beneficiario;
- `vopMatchedName`: nome suggerito, presente in caso di close match (codice `CMTC`);
- `vopResultDescription`: descrizione dell'errore VoP.

Esiti descritti dalla FAQ: MATCH, NO MATCH, CLOSE MATCH, NOT PARTIC, OUT OF SCOPE, NO APP, CHECK NOT POSSIBLE.

Valori reali della sandbox, cioè le etichette della FAQ e non i codici ISO:

| Caso | `vopMatchResult` | Altri header |
|---|---|---|
| match | `MATCH` | — |
| close match | `CLOSE MATCH` | `vopMatchedName: Paolo Bianchi` |
| verifica non possibile | `CHECK NOT POSSIBLE` | `vopResultDescription: NAMECHECK_01.000.A0000 - Generic Error` |
| simulazione rifiutata | `null` (la stringa) | — |

La 3.0 riconosce sia le etichette sia i codici ISO (`MTCH`, `CMTC`, `NMTC`, `NOAP`). I valori di Live non sono ancora verificati: serve una simulazione in Live, che va approvata a parte (D-55).

**Procedere con l'esecuzione equivale ad accettare l'esito VoP.** Per le persone giuridiche con un codice identificativo (es. TXID), se la verifica non è possibile si ripete la simulazione con la sola denominazione. La 2.x scarta gli header, quindi l'esito VoP non è disponibile.
- 3.0: l'esito è in `VopResult` sulle simulazioni. `execute()` richiede `acceptVopResult: true` se l'esito non è un match pieno, anche quando manca (D-36).
- Fonti: `SPEC`, `WEB`, `FAQ`.

### Q-38 Campi del bonifico SCT
- `currency` è obbligatoria; l'SCT è solo in EUR.
- `categoryPurpose` è opzionale; la 2.x invia sempre `CASH`.
- `requestedExecutionDate` è opzionale; la 2.x la invia sempre.
- 3.0: `SctPayment` ha come default `EUR` e `CASH`, entrambi configurabili. Senza data di esecuzione il campo viene omesso e decide la banca (la 2.x mandava sempre la data, e il gestionale passava quella del giorno).
- In sandbox il comportamento senza data non è verificabile: il caso di test richiede la data, e senza la sandbox risponde `WI02` (Q-51). Resta da verificare con una simulazione in Live.
- Fonti: `SPEC`, `PROD`, `SANDBOX`.

### Q-39 Caratteri ammessi
Nei testi (nomi, causale) sono ammessi solo i caratteri SEPA del set Latin: `a-z A-Z 0-9 / - ? : ( ) . , ' +` e lo spazio.
- 3.0: nessun controllo sui caratteri, come nella 2.x; c'è l'helper opzionale `SepaText::sanitize()` (D-39).
- Fonte: `WEB`.

### Q-40 Revoca SCT
È possibile solo entro i cut-off della banca, i cui orari non sono documentati. La chiamata è una `GET`.
- 3.0: `SctApi::revoke()` richiede almeno CRO o ID pagamento.
- Fonti: `WEB`, `SPEC`.

## Paginazione

### Q-41 Movimenti
Oltre i 200 record la risposta contiene `nextPage`, una URL completa con un parametro `offsetid` opaco che il client non deve costruire da sé.
- La spec mostra il link con il solo `offsetid`, mentre per i movimenti di fine giornata `date` è obbligatorio. La 2.x seguiva il link così com'era. La 3.0 aggiunge `date` al link se manca e non lo duplica se c'è già.
- La sandbox restituisce un link già completo di `date`, verso l'host pubblico (`…/twa/sandbox/v1/accounts/{IBAN}/transactions?date=20180201&offsetid=…`). Il caso di test restituisce però sempre la stessa pagina con lo stesso link, e la 3.0 si ferma con "Pagination loop".
- In Live non è stato possibile verificare una giornata con più di 200 movimenti: con meno di 200 movimenti `nextPage` è `null`.
- Fonti: `SPEC`, `LIVE`, `SANDBOX`.

### Q-42 Lista SCT Instant
- Parametri `offset` e `limit`: il default di `limit` è 50, la 2.x usa 100.
- `paymentDirection=O` è obbligatorio.
- Il link alla pagina successiva è in `links.next`.
- 3.0: `paymentDirection=O` viene aggiunto ai link che non lo hanno, e la lista si ferma su una pagina vuota anche se c'è un link successivo.
- In Live, con tutti i risultati in una pagina, la chiave `links` manca del tutto; in sandbox vale `null`.
- Fonti: `SPEC`, `PROD`, `LIVE`, `SANDBOX`.

### Q-43 Lista SCT
- `offset` è l'identificativo di un pagamento (stringa).
- La spec non prevede `limit` né `paymentDirection`, ma la 2.x li invia (`limit=30`, `paymentDirection=O`) e funziona.
- La 2.x si ferma quando `paymentsResults` è vuoto.
- 3.0: stessi parametri della 2.x (`offset=0`, `limit=30`, `paymentDirection=O`); `paymentDirection` viene aggiunto ai link che non lo hanno, e la lista si ferma su una pagina vuota.
- In Live i parametri della 2.x funzionano. Con 21 risultati in una sola pagina la risposta non ha `links` e riporta `pageSize: 1`, che quindi non è affidabile.
- Fonti: `SPEC`, `PROD`, `LIVE`.

### Q-44 Link di paginazione in sandbox
In sandbox i link di paginazione puntano a host irraggiungibili:
- movimenti: `https://external-api.syssede.systest.sanpaoloimi.com`;
- liste: `http://localhost:8081`, con l'IBAN fisso `IT59R0306901001100000002110`.

La 2.x li riscrive verso la base sandbox e l'IBAN configurato.
- 3.0 (`SandboxLinkRewriter`): l'host dei movimenti diventa l'host pubblico e il resto del percorso resta invariato. `http://localhost:8081` diventa `https://external-api.intesasanpaolo.com/twa/sandbox`, mentre la 2.x usava `/sandbox`, senza `/twa`, che con mTLS obbligatorio non è più disponibile.
- Verifiche del 12/11/2026: il link dei movimenti arriva già con l'host pubblico, quindi la riscrittura non serve più ma resta innocua. I link `localhost:8081` delle liste non si possono ottenere: con `limit=100` la lista SCT Instant ha `links: null`, e con altri valori di `limit` la sandbox risponde `WI02` (Q-51).
- Fonti: `PROD`, `SANDBOX`.

## Altre differenze

### Q-45 ISO 20022
Live usa `PAIN.001.001.03`, sandbox `pain.001.001.09`. Gli endpoint ISO non sono implementati.
- Fonte: `SPEC`.

### Q-46 SCT in sandbox
Non ci sono spec sandbox di simulazione e lista SCT, ma gli endpoint esistono: le spec sandbox del 2023 sono in `old/`.
- Fonti: `SPEC`, `SPEC-OLD`.

### Q-47 Base URL di `chargeabilityMulti`
La spec dichiara come base `/twa/live`, senza `/v1`, e mette `/v1` nel path: la URL finale è uguale a quella delle altre API. La risposta è dichiarata `*/*`.
- 3.0: la richiesta va su `{base}/tools/iban/chargeabilityMulti`, cioè `/twa/{ambiente}/v1/tools/iban/chargeabilityMulti`.
- Fonte: `SPEC`.

### Q-48 Certificato PKCS#12 di SmartHub e OpenSSL 3
Il file `.p12` scaricato da SmartHub protegge la chiave con un algoritmo legacy (`pbeWithSHA1And40BitRC2-CBC`). OpenSSL 3, usato da cURL e quindi da Guzzle, non lo legge senza il provider legacy. La richiesta fallisce con `cURL error 58: could not parse PKCS12 file` anche se la passphrase è giusta.
- Soluzione: convertire una volta il file in PEM, oppure in un PKCS#12 con cifratura moderna, con `openssl … -legacy`. I comandi sono nel README (sezione sul certificato).
- 3.0: la `TransportException` aggiunge al messaggio un suggerimento quando l'errore riguarda il PKCS#12.
- Fonte: `LIVE` (OpenSSL 3.5, cURL 8.14).

### Q-49 Millisecondi separati da `:` in sandbox
In sandbox gli `executionDate` e le `date` delle risposte hanno i millisecondi separati da due punti invece che dal punto, per esempio `2026-11-12T21:58:58:538+0100`. In Live gli stessi campi sono senza millisecondi (`2026-11-12T20:57:04+0000`).
- 3.0: `DateParser` accetta anche questa variante e la converte nella forma ISO.
- Fonti: `LIVE`, `SANDBOX`.

### Q-50 La stringa `"null"` come valore assente
Alcune risposte serializzano i valori mancanti come stringa `"null"`, per esempio l'esecuzione SCT Instant in stato `VRFY` (`orderId`, `paymentId`, `resubmitId`, `customerCro`, `currency`, `date`, BIC). Anche l'header `vopMatchResult` di una simulazione rifiutata vale `"null"`. Prima di questa correzione la 3.0 lanciava un'`UnexpectedResponseException` sulla data, cioè *dopo* che il bonifico era stato inviato.
- 3.0: `Payload` e gli header trattano `"null"` come valore assente, come `null` e la stringa vuota.
- Fonte: `SANDBOX`.

### Q-51 La sandbox è un mock a casi di test fissi
La sandbox non simula un conto: risponde solo agli input esatti dei casi di test della spec (IBAN `…XTESTXCASEX…`, importi, nomi, date, `limit`). Ogni altra richiesta riceve HTTP 403 con `WRONG_INPUT_EXCEPTION` e codice `WI02` ("The query parameter aren't included in the test case"). Le date delle risposte sono quelle del giorno.
- `chargeabilityMulti` senza codice fiscale né partita IVA risponde `WI05` ("At least one field among fiscalCode and vatNumber must be present"): vedi D-51.
- Alcuni casi di test sono rotti: saldo e movimenti di fine giornata di `IT04G0306901000XTESTXCASEX1` al 30/11/2017 rispondono HTTP 500 `G000`.
- Per questo i test di integrazione in sandbox usano i dati dei casi di test, e non i propri IBAN.
- Fonte: `SANDBOX`.
