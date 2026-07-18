# Calendario Eventi Automatico – Città di Campoformido

Piattaforma PHP completamente autonoma che genera e pubblica automaticamente una
locandina grafica degli eventi del Comune a partire dal feed XML del calendario
istituzionale.

L'obiettivo è eliminare ogni intervento manuale nella produzione della grafica,
garantendo che la locandina sia sempre aggiornata e coerente con gli eventi
pubblicati sul sito.

## Funzionalità

- acquisizione automatica degli eventi dal feed XML
- interpretazione e normalizzazione delle date in diversi formati (italiane,
  numeriche, iCalendar, ISO)
- filtro degli eventi passati e ordinamento cronologico
- selezione dei prossimi eventi da visualizzare (massimo configurabile)
- generazione dinamica della pagina HTML con layout responsive
- **doppio motore grafico** per la produzione del JPG:
  - **Playwright/Chromium** (se disponibile): screenshot della pagina HTML,
    resa identica al browser
  - **PHP GD** (fallback automatico): rendering nativo con lo stesso layout,
    funziona su qualsiasi hosting condiviso senza Node
- **variante ottimizzata per WhatsApp**: lato maggiore entro 1600 px, JPEG
  baseline, peso contenuto, così il canale WhatsApp la pubblica senza
  ricomprimerla e degradarla
- rilevamento delle modifiche tramite hash: nessuna elaborazione e nessuna
  email se gli eventi non sono cambiati
- invio automatico della locandina via email a più destinatari (entrambe le
  versioni in allegato)
- pagina di diagnostica per verificare i requisiti dell'hosting
- architettura modulare facilmente estendibile

## Struttura del progetto

| File | Ruolo |
|------|-------|
| `config.php` | Tutte le impostazioni: feed, destinatari email, percorsi, qualità immagini, limiti WhatsApp, chiave cron |
| `functions.php` | Motore dell'applicazione: lettura del feed, parsing date italiane, ordinamento, hash, scelta del motore grafico, variante WhatsApp |
| `index.php` | Pagina HTML della locandina (intestazione, logo, card eventi, footer) |
| `styles.css` | Tutta la grafica della versione HTML |
| `cron.php` | Punto di automazione: confronta l'hash, genera i JPG e invia l'email solo se ci sono novità |
| `image.php` | Genera/serve il JPG dal browser (`?force=1` rigenera, `?whatsapp=1` serve la variante WhatsApp) |
| `image-renderer.php` | Renderer PHP GD: disegna la locandina senza browser |
| `generate-image.mjs` | Renderer Playwright: fotografa la pagina HTML con Chromium |
| `test.php` | Diagnostica dell'hosting, anteprima eventi del feed e invio manuale dell'email |
| `assets/` | Logo comunale e font Inter (inclusi, licenza SIL OFL 1.1) |
| `output/` | JPG generati (`prossimi-eventi.jpg` e `prossimi-eventi-whatsapp.jpg`) |
| `data/` | Hash dell'ultima generazione e log del cron |

## Requisiti

- PHP 8.0 o superiore
- estensione **GD** con FreeType, JPEG e PNG (presente su quasi tutti gli hosting)
- estensione SimpleXML e cURL (o `allow_url_fopen`)
- facoltativo, per la resa Playwright: Node.js 18+ e `bash install-playwright.sh`

Il sistema sceglie da solo il motore migliore disponibile: se Node/Playwright
non sono utilizzabili (binario assente, `exec()` disabilitata, `node_modules`
mancante) passa automaticamente a GD e la locandina viene generata comunque.

## Installazione

1. Caricare i file sul server (o clonare il repository).
2. Aprire `config.php` e impostare:
   - `FEED_URL`: l'indirizzo del feed XML degli eventi
   - `DESTINATION_EMAILS`: i destinatari della locandina
   - `CRON_SECRET`: **una chiave lunga e casuale personale** (protegge
     l'esecuzione del cron via browser — non lasciare il valore di esempio)
3. Aprire `test.php` nel browser: la sezione "Ambiente di generazione" verifica
   tutti i requisiti (PHP, GD, font, permessi, Node) e indica quale motore
   grafico verrà usato.
4. Aprire `image.php?force=1` per generare la prima locandina.

## Utilizzo

| URL | Effetto |
|-----|---------|
| `index.php` | Locandina in versione HTML |
| `image.php` | JPG della locandina (generato se assente) |
| `image.php?force=1` | Rigenera il JPG |
| `image.php?whatsapp=1` | JPG ottimizzato per il canale WhatsApp |
| `test.php` | Diagnostica, anteprima del feed, invio manuale email |
| `cron.php?key=CHIAVE` | Esecuzione del ciclo automatico via web |

## Automazione (cron)

Riga di esempio per l'invio ogni lunedì alle 8:00:

```
0 8 * * 1 /usr/local/bin/php /percorso/del/progetto/cron.php >/dev/null 2>&1
```

A ogni esecuzione `cron.php`:

1. legge il feed e ne calcola l'hash;
2. se l'hash è identico all'ultima esecuzione **termina senza generare nulla e
   senza inviare email**;
3. altrimenti genera il JPG master e la variante WhatsApp, le invia in allegato
   ai destinatari configurati e aggiorna l'hash salvato.

Ogni passaggio viene registrato in `data/cron.log`.

L'email può essere inviata anche manualmente dal form in fondo a `test.php`
(richiede la `CRON_SECRET`), con la scelta se inviare comunque o solo in caso
di novità.

## Filosofia progettuale

- **single source of truth**: la grafica esiste una sola volta (HTML/CSS); il
  renderer GD ne replica il layout quando Chromium non è disponibile
- **modularità**: ogni componente svolge un compito specifico
- **automazione completa**: nessun intervento umano nella produzione
- **efficienza**: elaborazione solo quando gli eventi cambiano
- **scalabilità**: nuovi canali di distribuzione facilmente integrabili

## Possibili estensioni future

Pubblicazione automatica su Facebook e Instagram, invio tramite WhatsApp
Business API, canali Telegram, newsletter, monitor informativi, API REST,
esportazione PDF, layout alternativi, notifiche push, archivio storico delle
locandine generate.

## Licenze

I font [Inter](https://github.com/rsms/inter) inclusi in `assets/fonts` sono
distribuiti con licenza SIL Open Font License 1.1.
