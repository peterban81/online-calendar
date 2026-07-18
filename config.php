<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURAZIONE
|--------------------------------------------------------------------------
| Modificare soprattutto CRON_SECRET e, se necessario, MAIL_FROM.
*/

date_default_timezone_set('Europe/Rome');

const FEED_URL = 'https://eventi.impegnopercampoformido.it/feed-eventi.xml';
const CALENDAR_URL = 'https://www.comune.campoformido.ud.it';
const MAX_EVENTS = 5;

const DESTINATION_EMAILS = [
    'massimiliano.petri@gmail.com',
    // 'secondo.destinatario@example.com',
];
const MAIL_FROM = 'eventi@impegnopercampoformido.it';
const MAIL_FROM_NAME = 'Calendario eventi Campoformido';

/*
 * Sostituire questa stringa con una chiave lunga e casuale.
 * Serve per proteggere l'esecuzione del cron via browser.
 */
const CRON_SECRET = 'Sup3rC4lifragiliStichespirAlid0s0';

const DATA_DIR = __DIR__ . '/data';
const OUTPUT_DIR = __DIR__ . '/output';
const HASH_FILE = DATA_DIR . '/ultimo-hash.txt';
const LOG_FILE = DATA_DIR . '/cron.log';
const OUTPUT_JPG = OUTPUT_DIR . '/prossimi-eventi.jpg';

const POSTER_WIDTH = 1080;
const POSTER_HEIGHT = 1350;

/*
|--------------------------------------------------------------------------
| WHATSAPP
|--------------------------------------------------------------------------
| WhatsApp ricomprime le immagini con lato maggiore oltre ~1600 px.
| La variante WhatsApp viene ricavata dal JPG master e resta entro
| questi limiti, così il canale la pubblica senza degradarla.
*/
const OUTPUT_JPG_WHATSAPP = OUTPUT_DIR . '/prossimi-eventi-whatsapp.jpg';
const WHATSAPP_MAX_SIDE = 1600;
const WHATSAPP_JPG_QUALITY = 88;
const WHATSAPP_MAX_BYTES = 900 * 1024;

/*
 * false: alla prima esecuzione genera e invia.
 * true: alla prima esecuzione salva solo l'hash senza inviare.
 */
const SILENT_FIRST_RUN = false;


/*
|--------------------------------------------------------------------------
| QUALITÀ JPG E FONT
|--------------------------------------------------------------------------
| POSTER_SCALE = 2 genera un'immagine ad alta risoluzione:
| 2160 px di larghezza invece di 1080 px.
*/
const POSTER_SCALE = 2;
const JPG_QUALITY = 98;

/*
 * Font usati dal renderer PHP GD. I file Inter sono inclusi nel progetto;
 * se mancano, il codice ripiega sui font di sistema.
 */
const FONT_REGULAR_FILE = __DIR__ . '/assets/fonts/Inter-Regular.ttf';
const FONT_BOLD_FILE = __DIR__ . '/assets/fonts/Inter-Bold.ttf';


/*
|--------------------------------------------------------------------------
| PLAYWRIGHT / CHROMIUM (OPZIONALE)
|--------------------------------------------------------------------------
| Se Node e Playwright sono installati, il JPG viene fotografato da
| Chromium (resa identica al browser). Il binario Node viene cercato
| automaticamente nei percorsi tipici degli hosting; NODE_BINARY viene
| provato per primo. Se Node non è disponibile, il sistema usa
| automaticamente il renderer PHP GD: la grafica viene generata comunque.
*/
const NODE_BINARY = '/usr/bin/node';
const POSTER_URL = 'https://eventi.impegnopercampoformido.it/calendario-eventi/index.php?render=1';
