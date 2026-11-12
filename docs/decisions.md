# Decisioni di progettazione

Registro delle decisioni prese con la riscrittura 3.0 e dopo. Ogni voce indica la decisione, la motivazione ed eventuali alternative scartate. Il codice e la documentazione le citano con il loro identificativo (es. D-36).

- Le voci non si rinumerano e non si cancellano: una decisione superata si aggiorna indicando quella che la sostituisce.
- Il suffisso "— T*n*" indica la task della riscrittura in cui la decisione è stata presa (vedi [Task della riscrittura 3.0](#task-della-riscrittura-30)).
- Le decisioni prese dal maintainer sono indicate come tali.

## Decisioni

### D-01 · Major 3.0.0 su branch dedicato — 2026-11-12
Tutte le correzioni e i miglioramenti confluiscono in una nuova major 3.0.0, sviluppata sul branch `3.x`. Su Packagist il branch diventa `3.x-dev`, così il gestionale può provare la 3.0 prima del rilascio.
- Push di `3.x` al termine di ogni task. Nessun tag fino al rilascio.

### D-02 · Branch predefinito `main` — 2026-11-12
`master` rinominato in `main` su GitHub; la protezione (PR con 1 approvazione) è passata a `main`. Eliminato il branch `php80`, già interamente incluso.

### D-03 · Lingua
La documentazione è in **italiano**; codice, identificatori, PHPDoc, messaggi di eccezione e messaggi di commit in **inglese**.

### D-04 · Stile di codice moderno (Laravel-style)
Si abbandona lo stile della 2.x (tab, `if( ... )`, proprietà PascalCase, getter). Il codice nuovo segue:
- PER Coding Style, indentazione di 4 spazi, camelCase;
- `declare(strict_types=1);` in ogni file;
- classi `final` se non sono pensate per l'estensione;
- DTO `readonly` con **proprietà pubbliche** al posto dei getter, più metodi di comodo dove servono (es. `$vop->isMatch()`).

Lo strumento di formattazione è Laravel Pint (D-17).

### D-05 · PHP 8.3+, compatibile con 8.4 e 8.5
- Requisito `php: ^8.3`.
- Sintassi ammessa fino a PHP 8.3: classi readonly, enum, costanti tipizzate, `#[\Override]`. Nessuna funzionalità introdotta solo in 8.4+ (property hooks, visibilità asimmetrica).
- Niente costrutti deprecati in 8.4 o 8.5: nullable impliciti, cast non canonici come `(integer)`/`(boolean)`, `null` come indice di array.
- CI su 8.3, 8.4 e 8.5, con le deprecation trattate come errori.
- PHPUnit 12.5: è l'ultima versione che supporta 8.3 (la 13 richiede 8.4).

### D-06 · Guzzle `^7.15.2 || ^8.0.1`
Guzzle diventa una dipendenza diretta e si rimuove `php-http/guzzle7-adapter`, che non viene usato.
- Il vincolo include la 7 perché il gestionale è su Guzzle 7 e perché Guzzle 8 non funziona con l'estensione PECL `psr` (D-20).
- I minimi 7.15.2 e 8.0.1 escludono le versioni con advisory di sicurezza note (aggiornamento del 2026-11-12, in T2).
- Il client Guzzle si può iniettare, così i test possono usare un `MockHandler`.
- Compatibilità con Guzzle 8 verificata in T2 sul codice 2.x: `tests/Unit/Legacy/ClientTest.php` passa con Guzzle 7.15.2 e 8.4.0.
- Indicazioni per il codice 3.0, dalla guida di upgrade di Guzzle 8:
  - gli errori di rete si intercettano con `Psr\Http\Client\NetworkExceptionInterface`, quelli con risposta con `BadResponseException` (valido per entrambe le versioni);
  - i valori degli header devono essere sempre stringhe;
  - i metodi HTTP vanno scritti in maiuscolo.

### D-07 · Date senza Carbon
- Input: `DateTimeInterface` (quindi anche Carbon).
- Output: `DateTimeImmutable`.
- Ogni campo si legge con il suo formato esplicito ([api-quirks.md](api-quirks.md), Q-10).
- Le date senza orario si interpretano a mezzanotte in `Europe/Rome`.

Motivazione: richiedere Carbon imporrebbe una versione a chi usa la libreria (il gestionale è su Carbon 2, l'attuale è la 3) e non risolverebbe il problema, perché `Carbon::parse()` sbaglia giorno e mese esattamente come `DateTime`. Chi usa Carbon converte con `CarbonImmutable::instance()` o `new Carbon($date)`; la seconda strada è verificata con Carbon 2.73.

### D-08 · Spec ISP e dati reali fuori dal repo
Le spec scaricate dall'area riservata restano un riferimento locale ([specs-reference.md](specs-reference.md)). Nel repo non entrano mai dati reali: IBAN, CRO, nominativi, credenziali, certificati o risposte di produzione non anonimizzate.

### D-09 · Ristrutturazione insieme ai fix
I bugfix si fanno riscrivendo un'area alla volta nella nuova struttura (T3–T6), non prima sul codice 2.x: altrimenti molte correzioni andrebbero fatte due volte. Le incompatibilità con la 2.x si documentano in `UPGRADE-3.0.md`.

### D-10 · Struttura di destinazione
Una facade con sotto-API per area: `accounts()`, `sctInstant()`, `sct()`, `ibanTools()`. Sotto di essa:

```
src/
  IntesaSanPaoloClient.php   facade
  Config/                    ClientConfig (readonly), Environment (enum), MutualTlsCertificate
  Http/                      Transport, TokenProvider, Paginator, ApiResponse
  Api/                       AccountsApi, SctInstantApi, SctApi, IbanToolsApi
  Request/                   richieste immutabili e validate
  Response/                  DTO readonly costruiti con fromPayload()
  Enum/                      stati, esiti di validazione, esito VoP
  Support/                   DateParser, Payload (lettura tipizzata e tollerante)
  Exception/                 gerarchia di eccezioni con un'interfaccia comune
```

Nomi: il termine "Delayed" della 2.x diventa "Sct", che è la terminologia ISP per il bonifico ordinario; "Instant" diventa "SctInstant". I dettagli si definiscono in T3 e si documentano in `docs/architecture.md`.

Raffinamento in T3: le classi del token OAuth2 (`AccessToken`, `TokenStore`, `InMemoryTokenStore`, `TokenProvider`) stanno in `Auth/` invece che in `Http/`, perché in parte fanno parte dell'API pubblica.

### D-11 · Stati tolleranti
Gli stati sono enum, ma il valore originale (`raw`) è sempre conservato.
- L'enum si ricava dal primo token del valore, quindi `ACSC acquired` diventa `ACSC`.
- Si accettano gli alias noti (`PDNG` per `PNDG`, `Cancelled` per `Canceled`).
- Un valore sconosciuto non lancia eccezioni: l'enum vale `null` e resta disponibile il `raw`.

Motivazione: le API restituiscono varianti non documentate ([api-quirks.md](api-quirks.md), Q-25 e seguenti).

### D-12 · Fuori ambito per la 3.0
Restano fuori: endpoint ISO 20022 (pain.001), localizzazione ATM e filiali, incassi MAV. Si possono riconsiderare in seguito.

### D-13 · Nessun hotfix sulla 2.x
Un rilascio 2.2.0 con le sole correzioni urgenti (VoP, resubmit, date) è stato proposto ma non richiesto: non è pianificato.

### D-14 · Area di lavoro temporanea
Durante la riscrittura roadmap, finding e decisioni stavano nell'area temporanea `docs/v3/`, eliminata in T8 (D-60).

### D-15 · Finding lato consumer fuori da questo repo
Il repo è pubblico: i problemi trovati nel gestionale interno (task T9) non si tracciano qui, ma nel repository del gestionale.

### D-16 · `composer.lock` non versionato — T2
Per una libreria il lock non ha effetto su chi la installa e darebbe una falsa garanzia. La CI prova invece sia le dipendenze minime sia le massime consentite.

### D-17 · Formatter: Laravel Pint — T2
Si usa Laravel Pint con il preset `laravel`, coerente con D-04, più le regole `declare_strict_types`, `strict_comparison` e `strict_param` (`pint.json`). Pint richiede PHP 8.3, in linea con D-05.

### D-18 · Codice 2.x escluso dagli strumenti — T2
I file 2.x sono esclusi dall'analisi di PHPStan e da Pint finché non vengono riscritti: riformattarli o "correggerli" per gli strumenti rischierebbe di cambiarne il comportamento (per esempio con `strict_types`), proprio mentre fanno da riferimento per la riscrittura. L'unica modifica fatta è stata rendere espliciti i nullable impliciti, che non cambia il comportamento. Il comportamento 2.x, compresi i quirk di trasporto, è fissato dai test in `tests/Unit/Legacy/`. Ogni esclusione si rimuove quando il file viene sostituito.

### D-19 · PHPStan livello max — T2
PHPStan è configurato al livello massimo, con `phpVersion` da 8.3 a 8.5, su `src` e `tests`. Senza baseline: il codice nuovo nasce già conforme. In CI l'analisi gira con le dipendenze massime, perché i PHPDoc generici di Guzzle 7 e 8 non sono compatibili tra loro.

### D-20 · Estensione PECL `psr` — T2
L'estensione PECL `psr` (1.2.0), presente in molti ambienti Phalcon, ridefinisce le interfacce PSR-7 senza tipi, e ci sono due conseguenze:
- con Guzzle 8 (psr7 3.x) PHP va in errore fatale (`Declaration of GuzzleHttp\Psr7\Request::withRequestTarget(string $requestTarget) must be compatible with PsrExt\...`);
- PHPStan legge come `mixed` tutti i metodi di `Psr\Http\Message\*`.

Decisione: il vincolo resta aperto anche a Guzzle 7 (D-06), il problema è documentato nel README e in `AGENTS.md`, e in locale l'analisi si esegue con un PHP senza l'estensione. Il codice 3.0 deve funzionare anche con le interfacce dell'estensione: il gestionale usa Phalcon e potrebbe girare con l'estensione attiva (in locale è presente).

### D-21 · Cache del token: interfaccia propria `TokenStore` — T3
Il token si condivide tra istanze con un'interfaccia `Auth\TokenStore` di tre metodi (`get`, `put`, `forget`). La predefinita è `InMemoryTokenStore`; `AccessToken::toArray()` e `fromArray()` servono a chi la implementa su una cache esterna.
- Alternative scartate: PSR-16, che porta una dipendenza esposta agli stessi conflitti dell'estensione PECL `psr` (D-20); nessuna cache, che lascerebbe un login per ogni istanza.
- Decisione del maintainer.

### D-22 · Nessun logger integrato — T3
L'SDK non registra log. Espone `transactionId` (header `X-ISP-Transaction-ID`) su risposte ed eccezioni e accetta un client Guzzle dell'applicazione, a cui si può aggiungere il middleware di log di Guzzle. Non ci sono dipendenze e non c'è rischio di registrare dati sensibili.
- Alternativa scartata: logger PSR-3 opzionale.
- Decisione del maintainer.

### D-23 · Certificato mTLS obbligatorio — T3
`ClientConfig` richiede un `MutualTlsCertificate` e la base URL è sempre `/twa/{ambiente}/v1`: tutte le spec attuali la usano, mentre il percorso senza `/twa/` viene dalle spec del 2020. I file del certificato vengono controllati alla creazione della configurazione. Un PKCS#12 deve avere estensione `.p12`, che Guzzle usa per indicare a cURL il tipo di certificato.
- Decisione del maintainer.

### D-24 · Timeout, nessun retry, gestione del 401 — T3
- Timeout predefiniti: 10 s per la connessione e 60 s per la richiesta, entrambi configurabili. Valori nulli o infiniti non sono ammessi, perché un daemon di pagamenti non deve bloccarsi.
- **Nessun retry automatico**: ripetere un'esecuzione di pagamento potrebbe eseguirla due volte. Una `TransportException` su un pagamento significa "esito sconosciuto".
- HTTP 401: il token in cache viene scartato e la chiamata fallisce con `AuthenticationException`, senza essere ripetuta.
- Il token si rinnova 60 s prima della scadenza. Senza `expires_in` valido vale per la sola richiesta corrente.

### D-25 · Errori HTTP gestiti dall'SDK, non da Guzzle — T3
Le richieste partono con `http_errors` disattivato, e il `Transport` classifica le risposte per stato: 401 → `AuthenticationException`, ≥ 400 → `ApiException`, `success: false` → `ApiException`, JSON non valido → `UnexpectedResponseException`.
- Gli errori di trasferimento si intercettano con `Psr\Http\Client\NetworkExceptionInterface` e `GuzzleHttp\Exception\GuzzleException`, che esistono sia in Guzzle 7 sia in Guzzle 8.
- La gerarchia di eccezioni di Guzzle 8, cambiata rispetto alla 7, non viene mai esposta.

### D-26 · API pubblica e interna — T3
Sono pubblici `Config/`, `Exception/` e, in `Auth/`, `AccessToken`, `TokenStore` e `InMemoryTokenStore`; da T4 anche la facade, le API, le richieste e i DTO di risposta. Tutto il resto è `@internal` (`Http/`, `Support/`, `TokenProvider`) e può cambiare senza una major.

### D-27 · Date in ingresso senza conversione di fuso — T3
Per i parametri di sola data l'SDK usa la data di calendario dell'oggetto ricevuto, senza convertirlo in `Europe/Rome`. Convertire potrebbe spostare il giorno in modo inatteso, per esempio le 23:30 UTC del 31/01 diventerebbero il 1° febbraio. In uscita le date senza orario sono a mezzanotte in `Europe/Rome` (D-07).

### D-28 · Tolleranza nella lettura delle risposte — T3
- Un campo mancante, `null`, stringa vuota o stringa `"null"` (D-57) non ha valore. Con più nomi alternativi vince il primo che ha un valore.
- Un valore del tipo sbagliato è un errore (`UnexpectedResponseException`, con il percorso del campo) e non viene convertito "a forza".
- `"payload": []` vale come payload assente, perché alcuni backend serializzano così un oggetto vuoto. Un array non vuoto resta un errore.

### D-29 · Ambiente predefinito: sandbox — T3
`ClientConfig` usa `Environment::Sandbox` se l'ambiente non è indicato: Live va scelto esplicitamente, così una configurazione incompleta non può muovere denaro.

### D-30 · Il client 2.x spostato in `Legacy\` — T4
Il client 2.x è diventato `Shellrent\OpenBanking\Legacy\IntesaSanPaoloClient`, marcato `@deprecated` e privato dei metodi dei conti. Il nome `Shellrent\OpenBanking\IntesaSanPaoloClient` è passato alla nuova facade. I pagamenti 2.x restano utilizzabili e coperti dai test come riferimento fino alla loro riscrittura. `Legacy\` viene eliminato entro T6, e comunque prima del rilascio.
- Alternative scartate: eliminare subito il client 2.x, che avrebbe lasciato il branch senza pagamenti e senza i test di riferimento dei quirk; dare un nome nuovo alla facade, che avrebbe cambiato per sempre il nome pubblico.
- Decisione del maintainer.

### D-31 · Le API paginate raccolgono tutte le pagine — T4
Come nella 2.x, i metodi delle liste restituiscono un unico oggetto con le transazioni di tutte le pagine. Gli iteratori lazy restano possibili in futuro senza rompere questa API. Le liste espongono i totali dichiarati dalla banca e `isComplete()`, perché un elenco incompleto (per esempio per un problema di paginazione) non deve passare inosservato in una riconciliazione.

### D-32 · DTO con i nomi dell'API, enum più valore originale — T4
Le proprietà dei DTO usano i nomi dei campi dell'API in camelCase: `additionalInfo` invece del `getReason()` della 2.x, `siaCode` per `SIACode`. Gli stati sono un enum (`status`), affiancato sempre dal valore originale (`rawStatus`). I campi testuali assenti valgono `null`, non stringa vuota.

### D-33 · Transaction ID nei DTO — T4
Le risposte singole espongono `transactionId`, le liste paginate `transactionIds` (uno per pagina): sono i riferimenti da dare al supporto ISP.

### D-34 · Date ISO sempre accettate — T4
`DateParser::date()` accetta sempre anche `Y-m-d`, oltre al formato atteso: non è ambiguo, e la 2.x lo accettava tramite `new DateTime()`. Accettarlo evita una regressione se l'API usa questo formato in campi senza esempi nella spec, come `accountingDate` e `valueDate`. La protezione sulle date `d/m/Y` resta invariata.

### D-35 · Movimenti di fine giornata: `date` mantenuta tra le pagine — T4
Il link `nextPage` è documentato con il solo `offsetid`, ma `date` è obbligatorio. La 3.0 aggiunge `date` al link se manca e non lo duplica se c'è già (Q-41). La sandbox invia già il link con `date`; in Live resta da verificare una giornata con più di 200 movimenti.

### D-36 · Blocco VoP in `execute()` — T5
`execute()` riceve l'oggetto simulazione ed esegue in automatico solo con esito VoP `Match`. In tutti gli altri casi lancia `VopConfirmationRequiredException`, finché il chiamante non passa `acceptVopResult: true` dopo aver valutato l'esito: close match, no match, banca non aderente, verifica impossibile, ma anche esito assente o sconosciuto.
- Per i flussi divisi tra più processi la simulazione si salva e si ricarica (`toArray()`/`fromArray()`), e il blocco resta attivo.
- `resubmit()` non ripete il controllo, perché prosegue un'esecuzione già accettata.
- Vale anche per l'SCT ordinario (T6).
- Alternativa scartata: lasciare il controllo all'applicazione.
- Decisione del maintainer.

### D-37 · Due classi di richiesta — T5
`Request\InstantPayment` (T5) e `Request\SctPayment` (T6) sono immutabili e validate alla creazione, con regole comuni in `Request\Validator`. Ognuna ha solo i campi del suo bonifico. L'IBAN del debitore è opzionale e il default è quello della configurazione; il nome del debitore è obbligatorio, perché la configurazione non lo contiene.
- Decisione del maintainer.

### D-38 · Importi float validati e JSON deterministico — T5
- Gli importi restano `float`: sono positivi, finiti e hanno al massimo 2 decimali. Il confronto avviene in centesimi con tolleranza 1e-6, quindi `0.1 + 0.2` diventa `0.3` e `10.567` viene rifiutato.
- Tutti i body JSON passano da `Support\Json`, che imposta temporaneamente `serialize_precision = -1` e usa gli stessi flag dell'opzione `json` di Guzzle usata dalla 2.x. I byte inviati sono identici a quelli della 2.x, salvo il rumore dei float.
- Decisione del maintainer. Risolve la codifica non deterministica degli importi della 2.x (`0.1 + 0.2` inviato come `0.30000000000000004`).

### D-39 · Testi: solo lunghezze, più l'helper `SepaText` — T5
L'SDK controlla i campi obbligatori e le lunghezze SEPA: nomi 70 caratteri, causale 140, `endToEndId` 35. Le lunghezze si contano in caratteri UTF-8, non in byte. I caratteri si inviano così come sono, come nella 2.x, che funziona in produzione con lettere accentate.
- `Support\SepaText` (pubblico) offre `isValid()` e `sanitize()`. `sanitize()` traslittera le lettere accentate, converte `&` in `+` e gli apostrofi tipografici in `'`, e rimuove gli altri caratteri non ammessi.
- La documentazione avverte che modificare il nome del creditore può cambiare l'esito VoP.
- Decisione del maintainer.

### D-40 · `execute()` controlla la simulazione — T5
Prima di eseguire, l'SDK verifica che la simulazione sia accettata e che appartenga al pagamento: stesso IBAN del creditore e stesso importo, quando la risposta li riporta. Così l'esito VoP di una simulazione non può essere usato per un pagamento diverso.

### D-41 · `NOTPROVIDED` vale come assenza — T5
L'`endToEndId` `NOTPROVIDED` restituito dalla banca, con o senza spazi, diventa `null` nelle risposte e viene ignorato nelle richieste (Q-35), come già faceva il gestionale.

### D-42 · Enum degli stati di pagamento — T5
- `PaymentStatus` usa i codici ISO 20022 ed è condiviso con l'SCT: `ACSC`, `ACSP`, `ACTC`, `PNDG`/`PDNG`, `RJCT`, `VRFY`, `CANC`, `RETU`. `isAccepted()` vale per `ACSC`, `ACSP` e `ACTC`, cioè ordini da non ripagare. `isFinal()` vale per gli stati che non cambiano più.
- `ValidationStatus`: `ACCP` più l'alias `ACSC`.
- `InstantStatus`: gli stati testuali, con l'alias `Cancelled`.
- `VopMatchResult` riconosce sia i codici (`MTCH`, `CMTC`, `NMTC`, `NOAP`) sia le etichette della FAQ. In sandbox l'header usa le etichette (`MATCH`, `CLOSE MATCH`, `CHECK NOT POSSIBLE`, Q-37); i valori di Live restano da verificare.

### D-43 · Lista SCT Instant — T5
- Senza data di inizio vale il mese precedente, come nella 2.x.
- Le pagine sono da 100 pagamenti.
- `paymentDirection=O` viene aggiunto ai link che non lo hanno.
- La lista si ferma su una pagina vuota anche se c'è un link successivo, come nella 2.x.

### D-44 · Resubmit come metodo separato — T5
`resubmit($payment, $simulationId, $resubmitId)` riceve gli identificativi invece degli oggetti, perché tipicamente avviene in un secondo momento, a partire da dati salvati. Invia sempre insieme `resubmit: true` e `resubmitId`, mentre la 2.x ignorava `resubmitId` senza `setResubmit(true)` (Q-36).

### D-45 · Default della richiesta SCT — T6
- `SctPayment` usa `EUR` e `CASH` come default (il `CASH` era fisso nella 2.x), entrambi configurabili e validati come codici: ISO 4217 a 3 lettere, ISO 20022 a 4 lettere.
- `requestedExecutionDate` è opzionale: senza data il campo non viene inviato, come prevede la spec. La 2.x lo inviava sempre. In sandbox l'effetto dell'omissione non è verificabile (Q-38): resta da verificare con una simulazione in Live.
- `CurrencySet` viene eliminato: era incompleto e superfluo, perché l'SCT è solo in EUR.

### D-46 · Quirk SCT mantenuti — T6
- `Content-Type: application/json` sulle GET di stato e lista, su ogni pagina, ma non sulla revoca, come nella 2.x (Q-04).
- Lista: stessi parametri della 2.x (`offset=0`, `limit=30`, `paymentDirection=O`), `paymentDirection` aggiunto ai link successivi, stop su pagina vuota (Q-43).
- `status()` accetta i quattro identificativi della spec, di cui almeno uno obbligatorio; `revoke()` richiede CRO o ID pagamento.

### D-47 · Controlli di esecuzione condivisi — T6
I controlli prima dell'esecuzione (D-36, D-40) stanno in un'unica classe interna, `Api\PaymentGuard`, usata sia da `SctInstantApi` sia da `SctApi`: le due API applicano esattamente le stesse regole.

### D-48 · Esito della revoca — T6
`SctRevocation::$revoked` vale `true` per `OK`, `false` per `KO` (anche `KO - Generic error`, D-58) e `null` per un valore sconosciuto. `isRevoked()` è vero solo con una conferma esplicita. Il valore originale resta in `$rawStatus`.

### D-49 · Date SCT — T6
- Le date SCT si leggono come `d/m/Y`, con `Ymd` tollerato.
- `DateParser::date()` accetta sempre anche una data-ora ISO 8601 completa, non ambigua, e ne conserva l'ora. È un'estensione di D-34, perché la 2.x leggeva `executionDate` con `new DateTime()`.
- `revokeDate` unisce `revokeDate` e `revokeTime`, come nella 2.x.

### D-50 · Codice 2.x eliminato — T6
Con l'SCT riscritto sono stati eliminati `Legacy\IntesaSanPaoloClient`, `Models/`, `Exceptions/`, `DataSet/` e i test 2.x, insieme alle esclusioni da PHPStan e Pint. Questo chiude D-18 e D-30. Tutto `src/` è ora analizzato al livello max e formattato da Pint. I comportamenti 2.x fissati dai test di riferimento sono coperti dai test 3.0, che citano le voci Q-…; per confrontare un comportamento con la 2.x resta il tag `2.1.1`.

### D-51 · "Verifica esistenza IBAN" tramite `chargeabilityMulti` — T7
La spec scaricata per "Verifica esistenza IBAN" è una copia del formal-check, e il portale non dà altre indicazioni. L'esistenza si verifica quindi con `chargeabilityMulti` passando il solo IBAN (`ibanTools()->verify($iban)`): la sua descrizione dice "Check IBAN existence in addition to verification of IBAN holder", e codice fiscale e partita IVA sono opzionali. In sandbox la chiamata senza codice fiscale né partita IVA è rifiutata (`WI05`, Q-51): va confermata in Live (D-55).
- Decisione del maintainer.

### D-52 · Raggiungibilità Instant in `sctInstant()` — T7
La verifica di raggiungibilità SCT Instant è `sctInstant()->reachability()` e non sta negli strumenti IBAN: risponde alla domanda "posso pagare questo IBAN in istantaneo?" e precede la simulazione Instant. Gli strumenti IBAN (`ibanTools()`) non dipendono dal conto configurato.

### D-53 · Formal-check senza rifiuto locale — T7
`formalCheck()` normalizza l'IBAN (spazi, maiuscole) ma non lo valida da sé: un IBAN non valido è un esito dell'API (`isValid()` falso), non un'eccezione. Gli altri metodi che ricevono un IBAN lo validano prima della chiamata.

### D-54 · Codice fiscale e partita IVA — T7
`verify()` normalizza codice fiscale e partita IVA (spazi rimossi, maiuscole) e richiede solo 8–16 caratteri alfanumerici, senza controllo di checksum: le banche CBI Globe possono avere formati diversi, e la verifica vera la fa l'API.

### D-55 · Verifiche reali in Live, solo lettura — T8
Su indicazione del maintainer, in T8 si verificano i comportamenti ancora presunti con chiamate reali alle API di **sola lettura**:
- in Live, con le credenziali di produzione: saldo, movimenti, liste e stati dei pagamenti, strumenti IBAN, raggiungibilità;
- in sandbox: simulazioni ed esecuzioni.

Le verifiche si fanno con una suite o uno script lanciati esplicitamente, con credenziali e certificato locali mai versionati. La regola 6 di `AGENTS.md` ("mai chiamare Live") verrà aggiornata per ammettere solo questo caso. Una simulazione in Live (per leggere gli header VoP reali) non sposta denaro, ma richiede un'approvazione esplicita a parte.

Punti da verificare:
- valori reali dell'header `vopMatchResult` (D-42);
- comportamento dell'SCT senza `requestedExecutionDate` (D-45);
- parametro `date` sul link `nextPage` dei movimenti con più di 200 record (D-35);
- riscrittura `localhost:8081` → `/twa/sandbox` (Q-44);
- esistenza IBAN tramite `chargeabilityMulti` (D-51);
- tipi reali dei campi del formal-check (Q-19);
- formato di `links` nelle liste.

Esito delle verifiche del 12/11/2026, con la 3.0 (dettagli in [api-quirks.md](api-quirks.md), fonti `LIVE` e `SANDBOX`):
- Live, sola lettura: saldo, movimenti di oggi e di fine giornata, liste SCT e SCT Instant, stato SCT per CRO, ID pagamento ed end-to-end ID, raggiungibilità di IBAN italiani ed esteri. Tutto funziona.
- Sandbox: casi di test di saldo, movimenti, esecuzioni, stati, liste, revoca, strumenti IBAN e raggiungibilità.
- Valori dell'header VoP: etichette della FAQ in sandbox; Live da verificare con una simulazione, che richiede un'approvazione a parte.
- SCT senza `requestedExecutionDate`: non verificabile in sandbox, che ha solo il caso con la data.
- `nextPage` dei movimenti: in sandbox arriva già completo di `date`; in Live non c'era una giornata con più di 200 movimenti.
- `localhost:8081`: non si riesce a ottenere un link di questo tipo dalla sandbox (Q-44).
- Formal-check: in sandbox i campi sono stringhe (Q-19).
- `links`: assente in Live con una sola pagina, `null` in sandbox (Q-42, Q-43).
- Strumenti IBAN in Live: da verificare quando le API saranno abilitate sull'app OAuth di produzione.
- Nuovi quirk e correzioni: certificato PKCS#12 (Q-48), millisecondi (Q-49), stringa `"null"` (Q-50, D-57), esiti negativi con `success: false` (D-58), sandbox a casi di test fissi (Q-51).

### D-56 · `endToEndId` di al massimo 35 caratteri — T8
Quasi tutti i casi di test di simulazione ed esecuzione della sandbox usano come `endToEndId` un UUID di 36 caratteri. L'SDK mantiene comunque il limite SEPA di 35 caratteri (ISO 20022 `Max35Text`): un valore più lungo verrebbe rifiutato o troncato più avanti nel circuito interbancario. Quei casi di test sono stati verificati una volta con uno script esterno al repo, che usa le classi interne, e restano fuori dalla suite di integrazione.
- Alternativa scartata: alzare il limite a 36 solo per poter eseguire i casi di test.
- Proposta dell'agent, in attesa di conferma del maintainer.

### D-57 · La stringa `"null"` come valore assente — T8
`Payload` e la lettura degli header trattano la stringa `"null"` come `null` e la stringa vuota (Q-50). La sandbox la usa nell'esecuzione `VRFY` e nell'header `vopMatchResult` delle simulazioni rifiutate. Senza questa regola l'esecuzione lanciava un'`UnexpectedResponseException` sulla data dopo che il bonifico era già partito. Un valore reale `"null"` (per esempio un nome) non è plausibile.

Di conseguenza `execute()` documenta che anche dopo un'`UnexpectedResponseException`, o un'`ApiException` con stato 5xx, l'esito è sconosciuto, come dopo una `TransportException` (D-24).

### D-58 · Esiti negativi inviati con `success: false` — T8
`chargeabilityMulti` e la revoca SCT comunicano alcuni esiti negativi con HTTP 200, `success: false`, nessun errore e l'esito nel `payload` (Q-20, Q-31). La spec li elenca tra i casi di test con risposta 200. Per questi due endpoint, e solo per loro, il `Transport` restituisce il payload invece di lanciare un'`ApiException` (`acceptNegativeOutcome`): `verify()` restituisce `isAllowed()` falso e `revoke()` restituisce `revoked: false`. Con degli `errors`, o senza payload, resta un'`ApiException`.
- Per i pagamenti `success: false` resta sempre un errore.

### D-59 · Suite di integrazione opzionale — T8
I test contro le API reali stanno in `tests/Integration/`, nella suite PHPUnit `integration`: non fanno parte della suite predefinita né della CI (`composer test:integration`, [testing.md](testing.md)).
- Impostazioni da variabili d'ambiente o dal file `env/integration.env`, escluso da git. Senza configurazione i test vengono saltati.
- Sandbox: casi di test della spec con i loro dati fittizi.
- Live: solo lettura, attivata da `ISP_LIVE_READ_ONLY=yes`. Il client dei test blocca prima dell'invio ogni richiesta che non sia una `GET`, tranne quella del token. Le asserzioni non confrontano dati reali con valori attesi.
- La regola 6 di `AGENTS.md` ammette Live solo per questa suite, su richiesta esplicita del maintainer.

### D-60 · Documentazione della riscrittura — T8
Al rilascio l'area temporanea `docs/v3/` è stata eliminata:
- le decisioni restano in questo file, con gli stessi identificativi;
- i finding della revisione della 2.x, tutti risolti, sono descritti in `CHANGELOG.md` e `UPGRADE-3.0.md`;
- la roadmap è riassunta qui sotto; il dettaglio resta nella cronologia git.

### D-61 · Lettura tollerante delle risposte di esecuzione — T8
Le risposte di `execute()` e `resubmit()` (`InstantExecution`, `SctExecution`) si leggono con `Payload::lenient()`: un campo di tipo o formato inatteso vale `null` invece di lanciare un'`UnexpectedResponseException`. A quel punto la banca ha già agito, e un campo secondario non deve far perdere l'esito, l'`orderId` o il `resubmitId`. È successo davvero con la data `"null"` della sandbox (Q-50). I campi obbligatori mancanti restano un errore; le altre risposte restano lette in modo rigoroso (D-28).
- `UnexpectedResponseException` riporta anche l'inizio del corpo (`responseBody`) quando la risposta non è un JSON valido.
- Emerso dalla revisione finale del codice.

### D-62 · Il nome del creditore deve corrispondere alla simulazione — T8
L'esito VoP vale per il nome simulato. `PaymentGuard` confronta quindi anche il nome del creditore della richiesta con quello riportato dalla simulazione, oltre a IBAN e importo (D-40). Se sono diversi, `execute()` lancia un'`InvalidRequestException` e non invia nulla. Il confronto ignora maiuscole, spazi multipli e caratteri non SEPA, traslitterati con `SepaText::sanitize()`, così una normalizzazione della banca non blocca un pagamento corretto.
- In sandbox la simulazione riporta il nome inviato anche nel close match, mentre il nome suggerito sta solo nell'header (Q-37). Per pagare con il nome suggerito si simula di nuovo.
- Emerso dalla revisione finale del codice. Comportamento più restrittivo: da confermare dal maintainer.

### D-63 · Trasporto più difensivo — T8
Correzioni emerse dalla revisione finale del codice:
- un URL assoluto (cioè un link di paginazione) deve iniziare con l'host delle API ISP, altrimenti `UnexpectedResponseException` senza alcuna richiesta: credenziali e certificato non escono mai verso altri host;
- i redirect non vengono seguiti;
- i parametri di `Sender::send()` con le credenziali sono `#[SensitiveParameter]`;
- `success` vale come fallimento anche come stringa `"false"` o numero `0`; un `success` assente non è un fallimento;
- con `success: false`, un esito positivo nel payload (revoca `OK`, IBAN `allowed`) è contraddittorio e vale `null`, mai positivo (D-58);
- gli interi oltre `PHP_INT_MAX` restano stringhe esatte (`JSON_BIGINT_AS_STRING`);
- i testi delle richieste devono essere UTF-8 valido, altrimenti `InvalidRequestException` (prima il conteggio dei caratteri falliva in silenzio);
- i link di paginazione si accettano sia come URL sia come `{"next": …}` (Q-43).

## Task della riscrittura 3.0

| Task | Contenuto |
|---|---|
| T0 | branch `master` rinominato in `main`, creazione di `3.x` |
| T1 | documentazione per agent e documentazione tecnica, file di progetto |
| T2 | tooling: PHP 8.3+, Guzzle 7/8, PHPUnit 12, PHPStan max, Pint, CI |
| T3 | core: configurazione, autenticazione, trasporto, date, eccezioni |
| T4 | facade e API Conti |
| T5 | API SCT Instant, con blocco VoP |
| T6 | API SCT, controlli di esecuzione condivisi, eliminazione del codice 2.x |
| T7 | strumenti IBAN e raggiungibilità SCT Instant |
| T8 | verifiche reali in sandbox e Live, suite di integrazione, rilascio 3.0.0 |
| T9 | migrazione del gestionale, nel repository del gestionale (D-15) |
