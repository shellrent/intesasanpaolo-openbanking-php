# Riferimento alle spec OpenAPI di Intesa Sanpaolo

Le spec ufficiali delle API (file JSON in formato Swagger 2.0) si scaricano dall'area riservata di SmartHub: per ogni API attiva sull'app c'è una pagina con il link al JSON.

> **Le spec non vanno mai committate in questo repository**, né per intero né come copie estese del loro contenuto. Il repo è pubblico e le spec sono distribuite solo agli utenti registrati. Si tengono in locale come riferimento per sviluppare e aggiornare la libreria.

## Dove si trovano

Per convenzione le spec stanno in una cartella accanto al repo:

```
../openbanking-openapi/
├── *.json        spec Live attuali
├── sandbox/      spec Sandbox attuali
└── old/          generazioni precedenti (2020–2023)
```

Se serve un percorso interno al repo si può creare un link simbolico `specs`, già ignorato da git:

```bash
ln -s ../openbanking-openapi specs
```

## Leggere una spec

In Swagger 2.0, `host` + `basePath` danno la base URL, per esempio `external-api.intesasanpaolo.com/twa/live/v1`. Alcuni comandi utili:

```bash
# endpoint e metodi
jq -r '.paths | to_entries[] | .key as $p | .value | keys[] | "\(.) \($p)"' "Saldo_v1.json"

# header di risposta documentati (es. VoP, X-ISP-Transaction-ID)
jq '.paths[][].responses["200"].headers' "Simulazione Pagamento SCT_v1.json"

# schema del body di risposta 200
jq '.paths[][].responses["200"].schema' "Stato Pagamento SCT_v1.json"
```

## File canonici per API

Per ogni API va usato il file indicato. I doppioni sono elencati più sotto.

| Area | API | Endpoint | Live | Sandbox |
|---|---|---|---|---|
| Conti | Saldo | `GET /accounts/{IBAN}/balance` | `Saldo_v1.json` | `sandbox/Saldo_v1.json` |
| Conti | Movimenti di fine giornata | `GET /accounts/{IBAN}/transactions` | `Rendicontazione fine giornata_v1.json` | `sandbox/Rendicontazione fine giornata_v1.json` |
| Conti | Movimenti intraday | `GET /accounts/{IBAN}/transactions/today` | `Rendicontazione Intraday_v1.json` | `sandbox/Rendicontazione Intraday_v1.json` |
| SCT Instant | Simulazione | `POST /payments/sct/instant/simulation` | `Validazione Pagamenti SCT Inst_v1.json` | `sandbox/Validazione Pagamenti SCT Inst_v1.json` |
| SCT Instant | Esecuzione | `POST /payments/sct/instant` | `Validazione e spedizione pagamenti SCT Inst_v1.json` | `sandbox/Validazione e spedizione pagamenti SCT Inst_v1.json` |
| SCT Instant | Stato | `GET /payments/sct/instant/{IBAN}/history/{orderId}` | `Stato Pagamento SCT Inst_v1.json` | `sandbox/Stato Pagamento SCT Inst_v1.json` |
| SCT Instant | Lista | `GET /payments/sct/instant/{IBAN}/history` | `Lista Pagamenti SCT Inst_v1.json` | `sandbox/Lista Pagamenti SCT Inst_v1.json` |
| SCT Instant | Raggiungibilità IBAN | `GET /payments/sct/instant/reachability` | `Verifica raggiungibilità Instant SCT Inst_v1.json` | `sandbox/Verifica raggiungibilità Instant_v1.json` |
| SCT Instant | Simulazione ISO 20022 | `POST /payments/sct/instant/simulation/iso` | `Validazione Pagamenti ISO SCT Inst_v1.json` | `sandbox/Validazione Pagamenti ISO SCT Inst_v1.json` |
| SCT Instant | Esecuzione ISO 20022 | `POST /payments/sct/instant/iso` | `Validazione e spedizione pagamenti ISO SCT Inst_v1.json` | `sandbox/Validazione e spedizione pagamenti ISO SCT Inst_v1.json` |
| SCT | Simulazione | `POST /payments/bonsct/simulation` | `Simulazione Pagamento SCT_v1.json` | assente (vedi `old/`) |
| SCT | Esecuzione | `POST /payments/bonsct` | `Esecuzione pagamento SCT_v1.json` | `sandbox/Esecuzione pagamento_v1.json` |
| SCT | Stato | `GET /payments/bonsct/{iban}/history` | `Stato Pagamento SCT_v1.json` | `sandbox/Stato Pagamento SCT_v1.json` |
| SCT | Lista | `GET /payments/bonsct/{iban}/list` | `Lista Pagamenti SCT_v1.json` | assente (vedi `old/`) |
| SCT | Revoca | `GET /payments/bonsct/{iban}/revoke` | `Revoca Pagamento_v1.json` | `sandbox/Revoca Pagamento SCT_v1.json` |
| SCT | Simulazione ISO 20022 | `POST /payments/bonsct/simulation/iso` | `Simulazione pagamento ISO SCT_v1.json` | `sandbox/Simulazione pagamento ISO SCT_v1.json` |
| SCT | Esecuzione ISO 20022 | `POST /payments/bonsct/iso` | `Spedizione pagamento ISO SCT_v1.json` | `sandbox/Spedizione pagamento ISO SCT_v1.json` |
| Strumenti | Verifica sintattica IBAN | `GET /tools/iban/formal-check` | `Validazione sintattica IBAN_v1.json` | `sandbox/Validazione sintattica IBAN_v1.json` |
| Strumenti | Esistenza IBAN e congruenza CF/P.IVA | `GET /tools/iban/chargeabilityMulti` | `Verifica congruenza IBAN CF_P.IVA Multibanca_v1.json` | `sandbox/Verifica congruenza IBAN CF_P.IVA Multibanca_v1.json` |
| Strumenti | Verifica esistenza IBAN | `GET /tools/iban/chargeabilityMulti` con il solo IBAN (D-51) | `Verifica esistenza IBAN_v1.json` ⚠️ copia del formal-check | `sandbox/Verifica esistenza IBAN_v1.json` ⚠️ |
| Locator | ATM | `GET /locator/atm` | — | `sandbox/ATM Locator_v1.json` |
| Locator | Filiali | `GET /locator/branch` | — | `sandbox/Branch Locator_v1.json` |

Gli endpoint sono relativi a `https://external-api.intesasanpaolo.com/twa/{live|sandbox}/v1`. La spec di `chargeabilityMulti` dichiara come base `/twa/live` e mette `/v1` nel path: la URL finale è comunque la stessa delle altre API.

## Doppioni

Questi file Live sono **copie identiche byte per byte** del file canonico: è la stessa API salvata con due nomi. Nessun doppione mescola SCT e SCT Instant. Si possono ignorare.

| Doppione | Copia di |
|---|---|
| `Esecuzione pagamento_v1.json` | `Esecuzione pagamento SCT_v1.json` |
| `Simulazione Pagamento_v1.json` | `Simulazione Pagamento SCT_v1.json` |
| `Lista Pagamenti_v1.json` | `Lista Pagamenti SCT_v1.json` |
| `Simulazione pagamento ISO_v1.json` | `Simulazione pagamento ISO SCT_v1.json` |
| `Spedizione pagamento ISO_v1.json` | `Spedizione pagamento ISO SCT_v1.json` |
| `Validazione Pagamenti_v1.json` | `Validazione Pagamenti SCT Inst_v1.json` |
| `Validazione Pagamenti ISO_v1.json` | `Validazione Pagamenti ISO SCT Inst_v1.json` |
| `Verifica raggiungibilità Instant_v1.json` | `Verifica raggiungibilità Instant SCT Inst_v1.json` |

## Anomalie note

- **"Verifica esistenza IBAN" contiene la spec del formal-check**, sia Live sia sandbox (file identici a "Validazione sintattica IBAN"). Può essere un errore del portale, oppure l'esistenza dell'IBAN si verifica con `chargeabilityMulti`: la sua descrizione dice *"Check IBAN existence in addition to verification of IBAN holder"*, e SmartHub documenta esistenza e congruenza in un'unica pagina. Il controllo nel portale non ha dato altre indicazioni. **Decisione (D-51)**: l'esistenza si verifica con `chargeabilityMulti` passando il solo IBAN (`ibanTools()->verify($iban)`), da confermare con le verifiche reali di T8.
- **In sandbox mancano le spec di simulazione e lista SCT.** Gli endpoint esistono: in `old/` ci sono le spec sandbox del 2023.
- **ISO 20022: versioni diverse.** Live usa `PAIN.001.001.03`, sandbox `pain.001.001.09`.
- **Differenze di valori tra Live e sandbox** (dettagli in [api-quirks.md](api-quirks.md)): l'esito della simulazione SCT Instant vale `ACSC` in Live e `ACCP` in sandbox; lo stato Instant è `Cancelled` in Live e `Canceled` in sandbox.

## Generazioni precedenti (`old/`)

| Generazione | File | Base URL | Note |
|---|---|---|---|
| 2020, senza mTLS | `live_*`, `sandbox_*` | `/live/v1`, `/sandbox/v1` | prime versioni di saldo, movimenti e SCT Instant |
| 2021, mTLS | `mutual_auth_*` (conti e SCT Instant) | `/twa/live`, `/twa/sandbox/v1` | introduzione della mutua autenticazione |
| 2023, SCT | `mutual_auth_*_bonsct_*` | `/twa/{live,sandbox}/v1` | prime spec del bonifico SCT ordinario |

Per scrivere codice valgono le spec attuali. Quelle vecchie servono a capire l'origine di alcuni workaround della libreria, per esempio `commissioni`, `order-id`, `ACCP accepted` e la base URL senza `/twa/`. Sono tutte citate in [api-quirks.md](api-quirks.md).

Novità delle spec attuali rispetto all'ultima generazione `old/`:
- header VoP (`vopMatchResult`, `vopMatchedName`, `vopResultDescription`) nelle simulazioni;
- stati `ACTC` (SCT) e `VRFY` (SCT Instant);
- `simulationId` obbligatorio nell'esecuzione SCT Instant;
- `endToEndId` e `simulationId` per ogni voce della lista SCT;
- `fees` al posto di `commissioni` nelle risposte SCT.
