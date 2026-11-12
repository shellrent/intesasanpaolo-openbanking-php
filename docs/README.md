# Documentazione

Documentazione tecnica dell'SDK. Per installazione e uso parti dal [README](../README.md) del repository; per lavorare sul codice leggi prima [AGENTS.md](../AGENTS.md).

## Riferimento alle API

| Documento | Contenuto |
|---|---|
| [api-coverage.md](api-coverage.md) | quali API SmartHub sono implementate e in quale task della 3.0 |
| [api-quirks.md](api-quirks.md) | comportamenti reali delle API, differenze dalle spec, formati delle date, stati, ognuno con la sua fonte |
| [specs-reference.md](specs-reference.md) | dove trovare le spec locali, quali file usare, doppioni e anomalie |

## Codice

| Documento | Contenuto |
|---|---|
| [architecture.md](architecture.md) | struttura del codice, percorso di una richiesta, eccezioni, token, API pubblica e interna |
| [dates-and-formats.md](dates-and-formats.md) | come l'SDK legge e scrive le date, fusi orari, uso con Carbon |
| [payment-flows.md](payment-flows.md) | simulazione, VoP, esecuzione, resubmit, stati, revoca, simulazioni salvate (SCT Instant e SCT) |
| [testing.md](testing.md) | comandi, struttura dei test, `MockHttp`, regole sulle fixture, test di integrazione con sandbox e Live |
| [adding-an-endpoint.md](adding-an-endpoint.md) | ricetta per integrare una nuova API, con l'esempio delle verifiche IBAN |
| [decisions.md](decisions.md) | decisioni di progettazione (D-01 e seguenti) e task della riscrittura 3.0 |
| [../UPGRADE-3.0.md](../UPGRADE-3.0.md) | aggiornamento dalla 2.x alla 3.0 |
