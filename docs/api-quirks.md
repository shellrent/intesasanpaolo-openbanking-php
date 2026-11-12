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
- Fonte: `PROD`.

### Q-05 Struttura della risposta ed errori
Ogni risposta ha la forma `{ "success": bool, "errors": ..., "payload": ... }`. Con `success` falso il `payload` è `null`.
- Errori (spec attuali): `errors[]` con `type`, `code`, `description`.
- La 2.x gestisce anche un formato alternativo con `title` e `detail.reason`.
- Le spec `old/` elencano i valori di `type`: `INPUT_EXCEPTION`, `EMPTY_INPUT_EXCEPTION`, `WRONG_INPUT_EXCEPTION`, `NULL_INPUT_EXCEPTION`, `APPLICATION_EXCEPTION`, `TECHINCAL_EXCEPTION` (sic), `UNDEFINED_EXCEPTION`, `DEV_EXCEPTION`, `NOT_FOUND_EXCEPTION`.
- Codici HTTP documentati: 400, 401, 403, 404, 405, 500 e 502 (sulle API SCT).
- Fonti: `SPEC`, `SPEC-OLD`, `PROD`.

### Q-06 Header `X-ISP-Transaction-ID`
Quasi tutte le risposte, anche di errore, contengono l'header `X-ISP-Transaction-ID`, l'identificativo della chiamata da indicare al supporto ISP. La 2.x lo scarta.
- Fonte: `SPEC`.

### Q-07 Header della vecchia spec del saldo
La spec mTLS del saldo del 2021 (variante "Business accounts") richiedeva gli header `X-ISP-Transaction-ID` e `ispwebservicesheader.businessinfo.customerid`. Le spec attuali non li prevedono, la 2.x non li invia e funziona: vanno ignorati.
- Fonti: `SPEC-OLD`, `PROD`.

### Q-08 Certificati mTLS
- Il certificato è unico per cliente e vale sia per Live sia per sandbox.
- Dura 1 anno; se ne possono avere al massimo 2 attivi, quindi il nuovo va generato e installato prima che scada il vecchio.
- Va configurato il trust dell'intera catena della CA, non del solo certificato.
- Formati supportati da Guzzle: PEM con chiave privata separata, oppure PKCS#12 (da Guzzle 7.3).
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
| Saldo | risposta | `executionDate` | ISO 8601 con offset | `2018-05-04T09:52:10.289+0200` |
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

### Q-11 Fuso orario
Le date senza orario sono date contabili italiane: si interpretano a mezzanotte nel fuso `Europe/Rome`. Le date ISO 8601 mantengono l'offset indicato. Vedi la decisione D-07 in `docs/v3/DECISIONS.md`.

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
- Fonti: `SPEC-OLD`, `SPEC`, `WEB`.

### Q-16 `settlementDate` (non `settlemenDate`)
Il nome corretto è `settlementDate`. `PaymentExecuted` della 2.x legge anche `settlemenDate`, un refuso senza fonte nelle spec.
- Fonti: `SPEC`, `WEB`.

### Q-17 Lista SCT: `executionInquiry`
Nella lista SCT la data di estrazione si chiama `executionInquiry`, non `executionDate`.
- Fonte: `SPEC`.

### Q-18 Raggiungibilità SCT Instant
I campi della risposta sono `isReachable`, `IBAN_Status` e `BIC`, con le maiuscole.
- Fonte: `SPEC`.

### Q-19 Verifica sintattica IBAN: campi "numerici"
`checkDigits`, `bankCode`, `bankAgency` e `account` sono dichiarati `number`, ma sono stringhe con zeri iniziali o lettere (es. `"03069"`, `"XTESTCASEX1"`): vanno trattati come stringhe.
- Fonti: `SPEC`, `WEB`.

### Q-20 `chargeabilityMulti`: esito
La spec restituisce `allowed` e `additionalInfo`. La pagina SmartHub mostra invece `IsAllowed`/`isAllowed`, con valore stringa `"true"`: l'esito va letto in modo tollerante.
- `additionalInfo` vale `AC15` se il conto è stato trasferito: esito OK se è rimasto nel gruppo ISP con nuove coordinate, KO se è passato a un'altra banca.
- Fonti: `SPEC`, `WEB`.

## Campi assenti o nulli

### Q-21 Saldo
Tutti i campi del saldo possono essere `null` o mancare.
- Fonte: `PROD` (commit `7a4d7ac`, `d07ec14`, `f46b6d4`).

### Q-22 Dati di debitore e creditore
IBAN e nomi di debitore e creditore possono mancare o essere `null` nelle risposte.
- Fonte: `PROD` (commit `1efb774`).

### Q-23 Movimenti
- I movimenti non contabilizzati (solo intraday) non hanno `uniqueId`, `date`, `accountingDate` né `additionalInfo`; il loro `status` è `unaccounted`.
- `valueDate` può essere vuoto o `"0"` (commit `6833c0b`); in quel caso la 2.x usa la data corrente.
- Lo `status` dei movimenti contabilizzati vale `accounted`, `reversed` o `canceled`.
- Fonti: `SPEC`, `WEB`, `PROD`.

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
- Fonti: `SPEC`, `SPEC-OLD`, `CONSUMER`.

### Q-26 Esito della simulazione SCT (`validationStatus`)
`ACCP` / `RJCT`.
- Fonte: `SPEC`.

### Q-27 Stato dell'esecuzione SCT Instant (`paymentStatus`)
`ACSC`, `PNDG`, `RJCT`, `VRFY`; `VRFY` è stato aggiunto dopo la spec 2021. La FAQ scrive `PDNG` invece di `PNDG`: vanno accettati entrambi.
- Fonti: `SPEC`, `SPEC-OLD`, `FAQ`.

### Q-28 Stato dell'esecuzione SCT (`paymentStatus`)
`ACSP`, `RJCT`, `VRFY`, `ACTC`; `ACTC` è stato aggiunto dopo la spec 2023.
- Fonti: `SPEC`, `SPEC-OLD`.

### Q-29 Stato e lista SCT (`paymentStatus`)
`ACSP`, `RJCT`, `CANC`, `ACSC`, `RETU`, `ACTC`.
- Fonte: `SPEC`.

### Q-30 Stato e lista SCT Instant (`status`)
`Completed`, `Rejected`, `Returned`, più `Cancelled` nello stato Live oppure `Canceled` nella lista e in sandbox.
- Fonte: `SPEC`.

### Q-31 Revoca SCT (`status`)
`OK` / `KO`.
- Fonte: `SPEC`.

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
- Fonte: `PROD` (commit `2bcbaea`, versione 1.1.2).

### Q-35 `endToEndId` non fornito
Quando non viene fornito un `endToEndId`, la banca restituisce `NOTPROVIDED` (oppure `NOT PROVIDED` negli esempi). Il gestionale lo tratta come valore assente.
- Fonti: `WEB`, `CONSUMER`.

### Q-36 Resubmit (SCT Instant)
Se l'esecuzione resta in `PNDG`, la risposta contiene un `resubmitId`; per riprovare si invia `resubmit: true` insieme al `resubmitId`. La 2.x invia questi campi solo se si è chiamato `setResubmit(true)`.
- Fonte: `SPEC`.

### Q-37 Verification of Payee (VoP)
Le simulazioni (SCT e SCT Instant, sia JSON sia ISO) restituiscono l'esito VoP in tre header di risposta:
- `vopMatchResult`: esito del confronto tra IBAN e nome del beneficiario;
- `vopMatchedName`: nome suggerito, presente in caso di close match (codice `CMTC`);
- `vopResultDescription`: descrizione dell'errore VoP.

Esiti descritti dalla FAQ: MATCH, NO MATCH, CLOSE MATCH, NOT PARTIC, OUT OF SCOPE, NO APP, CHECK NOT POSSIBLE. I codici esatti negli header vanno verificati su una risposta reale.

**Procedere con l'esecuzione equivale ad accettare l'esito VoP.** Per le persone giuridiche con un codice identificativo (es. TXID), se la verifica non è possibile si ripete la simulazione con la sola denominazione. La 2.x scarta gli header, quindi l'esito VoP non è disponibile.
- Fonti: `SPEC`, `WEB`, `FAQ`.

### Q-38 Campi del bonifico SCT
- `currency` è obbligatoria; l'SCT è solo in EUR.
- `categoryPurpose` è opzionale; la 2.x invia sempre `CASH`.
- `requestedExecutionDate` è opzionale; la 2.x la invia sempre.
- Fonti: `SPEC`, `PROD`.

### Q-39 Caratteri ammessi
Nei testi (nomi, causale) sono ammessi solo i caratteri SEPA del set Latin: `a-z A-Z 0-9 / - ? : ( ) . , ' +` e lo spazio.
- Fonte: `WEB`.

### Q-40 Revoca SCT
È possibile solo entro i cut-off della banca, i cui orari non sono documentati. La chiamata è una `GET`.
- Fonti: `WEB`, `SPEC`.

## Paginazione

### Q-41 Movimenti
Oltre i 200 record la risposta contiene `nextPage`, una URL completa con un parametro `offsetid` opaco che il client non deve costruire da sé.
- Fonte: `SPEC`.

### Q-42 Lista SCT Instant
- Parametri `offset` e `limit`: il default di `limit` è 50, la 2.x usa 100.
- `paymentDirection=O` è obbligatorio.
- Il link alla pagina successiva è in `links.next`.
- Fonti: `SPEC`, `PROD`.

### Q-43 Lista SCT
- `offset` è l'identificativo di un pagamento (stringa).
- La spec non prevede `limit` né `paymentDirection`, ma la 2.x li invia (`limit=30`, `paymentDirection=O`) e funziona.
- La 2.x si ferma quando `paymentsResults` è vuoto.
- Fonti: `SPEC`, `PROD`.

### Q-44 Link di paginazione in sandbox
In sandbox i link di paginazione puntano a host irraggiungibili:
- movimenti: `https://external-api.syssede.systest.sanpaoloimi.com`;
- liste: `http://localhost:8081`, con l'IBAN fisso `IT59R0306901001100000002110`.

La 2.x li riscrive verso la base sandbox e l'IBAN configurato.
- 3.0 (`SandboxLinkRewriter`): l'host dei movimenti diventa l'host pubblico e il resto del percorso resta invariato. `http://localhost:8081` diventa `https://external-api.intesasanpaolo.com/twa/sandbox`, mentre la 2.x usava `/sandbox`, senza `/twa`, che con mTLS obbligatorio non è più disponibile. Il comportamento va verificato con i test in sandbox (T8).
- Fonte: `PROD`.

## Altre differenze

### Q-45 ISO 20022
Live usa `PAIN.001.001.03`, sandbox `pain.001.001.09`. Gli endpoint ISO non sono implementati.
- Fonte: `SPEC`.

### Q-46 SCT in sandbox
Non ci sono spec sandbox di simulazione e lista SCT, ma gli endpoint esistono: le spec sandbox del 2023 sono in `old/`.
- Fonti: `SPEC`, `SPEC-OLD`.

### Q-47 Base URL di `chargeabilityMulti`
La spec dichiara come base `/twa/live`, senza `/v1`, e mette `/v1` nel path: la URL finale è uguale a quella delle altre API. La risposta è dichiarata `*/*`.
- Fonte: `SPEC`.
