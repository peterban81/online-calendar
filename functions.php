<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/*
 * Valori di riserva per installazioni con un config.php precedente:
 * ogni costante mancante viene definita qui con il suo valore standard,
 * così l'aggiornamento del codice non richiede di rifare il config.
 */
$configDefaults = [
    'NODE_BINARY' => '/usr/bin/node',
    'POSTER_URL' => 'https://eventi.impegnopercampoformido.it/calendario-eventi/index.php?render=1',
    'OUTPUT_JPG_WHATSAPP' => OUTPUT_DIR . '/prossimi-eventi-whatsapp.jpg',
    'WHATSAPP_MAX_SIDE' => 1600,
    'WHATSAPP_JPG_QUALITY' => 88,
    'WHATSAPP_MAX_BYTES' => 900 * 1024,
    'FONT_REGULAR_FILE' => __DIR__ . '/assets/fonts/Inter-Regular.ttf',
    'FONT_BOLD_FILE' => __DIR__ . '/assets/fonts/Inter-Bold.ttf',
];

foreach ($configDefaults as $constantName => $defaultValue) {
    if (!defined($constantName)) {
        define($constantName, $defaultValue);
    }
}

unset($configDefaults, $constantName, $defaultValue);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanText(?string $value): string
{
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/\x{00A0}/u', ' ', $value) ?? $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}

function localName(string $name): string
{
    $parts = explode(':', $name);
    $name = end($parts) ?: $name;
    return strtolower(preg_replace('/[^a-z0-9à-ÿ]/ui', '', $name) ?? $name);
}

/**
 * Cerca un valore non vuoto tra i discendenti XML.
 * L'ordine della lista $wantedNames determina la priorità: il primo nome
 * presente nell'item vince, indipendentemente dalla posizione nel
 * documento (es. data_inizio batte event_date anche se compare dopo).
 */
function xmlNodeValue(SimpleXMLElement $node, array $wantedNames): string
{
    $nodes = $node->xpath('.//*') ?: [];
    array_unshift($nodes, $node);

    $valuesByName = [];
    foreach ($nodes as $candidate) {
        $dom = dom_import_simplexml($candidate);
        if (!$dom) {
            continue;
        }

        $key = localName($dom->localName ?: $dom->nodeName);
        if (isset($valuesByName[$key])) {
            continue;
        }

        $value = cleanText((string)$candidate);
        if ($value !== '') {
            $valuesByName[$key] = $value;
        }
    }

    foreach ($wantedNames as $wanted) {
        $key = localName($wanted);
        if (isset($valuesByName[$key])) {
            return $valuesByName[$key];
        }
    }

    return '';
}

function fetchFeed(string $url = FEED_URL): string
{
    $headers = [
        'Accept: application/xml,text/xml,application/rss+xml,*/*',
        'User-Agent: CampoformidoEventPoster/1.0',
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if (is_string($body) && $body !== '' && $status >= 200 && $status < 400) {
            return $body;
        }

        throw new RuntimeException("Feed non raggiungibile via cURL. HTTP {$status}. {$error}");
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'header' => implode("\r\n", $headers),
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if (!is_string($body) || trim($body) === '') {
        throw new RuntimeException('Feed non raggiungibile. Abilitare cURL o allow_url_fopen sul server.');
    }

    return $body;
}

function italianMonths(): array
{
    return [
        'gennaio' => 1,
        'febbraio' => 2,
        'marzo' => 3,
        'aprile' => 4,
        'maggio' => 5,
        'giugno' => 6,
        'luglio' => 7,
        'agosto' => 8,
        'settembre' => 9,
        'ottobre' => 10,
        'novembre' => 11,
        'dicembre' => 12,
    ];
}

function makeDate(int $year, int $month, int $day, int $hour = 0, int $minute = 0): ?DateTimeImmutable
{
    if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59) {
        return null;
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))
        ->setDate($year, $month, $day)
        ->setTime($hour, $minute);
}

function extractTimeParts(string $text): array
{
    if (preg_match('/(?:\bore?\s*)?(\d{1,2})[.:](\d{2})\b/ui', $text, $match)) {
        return [(int)$match[1], (int)$match[2]];
    }

    return [0, 0];
}

/**
 * Estrae una data (con eventuale orario) da un testo nei formati
 * riconosciuti: ISO 2026-09-05, italiano "23 luglio 2026", numerico
 * 23/07/2026 e iCalendar 20260723T180000.
 */
function parseDateFromText(string $source, string $explicitTime): ?DateTimeImmutable
{
    $months = italianMonths();
    $monthPattern = implode('|', array_keys($months));

    // ISO: 2026-09-05, eventualmente seguita da un orario
    if (preg_match('/\b(\d{4})-(\d{1,2})-(\d{1,2})(?:\D{1,10}?(\d{1,2})[.:](\d{2}))?/u', $source, $m)) {
        $hour = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0;
        $minute = isset($m[5]) && $m[5] !== '' ? (int)$m[5] : 0;

        if (($hour === 0 && $minute === 0) && $explicitTime !== '') {
            [$hour, $minute] = extractTimeParts($explicitTime);
        }

        return makeDate((int)$m[1], (int)$m[2], (int)$m[3], $hour, $minute);
    }

    // 23 luglio 2026, ore 18.00
    if (preg_match(
        '/\b(\d{1,2})\s+(' . $monthPattern . ')\s+(\d{4})(?:\s*(?:,|-|–)?\s*(?:ore?)?\s*(\d{1,2})[.:](\d{2}))?/ui',
        $source,
        $m
    )) {
        $hour = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0;
        $minute = isset($m[5]) && $m[5] !== '' ? (int)$m[5] : 0;

        if (($hour === 0 && $minute === 0) && $explicitTime !== '') {
            [$hour, $minute] = extractTimeParts($explicitTime);
        }

        return makeDate((int)$m[3], $months[mb_strtolower($m[2], 'UTF-8')], (int)$m[1], $hour, $minute);
    }

    // 23/07/2026 18:00
    if (preg_match('/\b(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2,4})(?:\D+?(\d{1,2})[.:](\d{2}))?/u', $source, $m)) {
        $year = (int)$m[3];
        if ($year < 100) {
            $year += 2000;
        }

        $hour = isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0;
        $minute = isset($m[5]) && $m[5] !== '' ? (int)$m[5] : 0;

        if (($hour === 0 && $minute === 0) && $explicitTime !== '') {
            [$hour, $minute] = extractTimeParts($explicitTime);
        }

        return makeDate($year, (int)$m[2], (int)$m[1], $hour, $minute);
    }

    // iCalendar: 20260723T180000
    if (preg_match('/\b(\d{4})(\d{2})(\d{2})T?(\d{2})?(\d{2})?/u', $source, $m)) {
        return makeDate(
            (int)$m[1],
            (int)$m[2],
            (int)$m[3],
            isset($m[4]) && $m[4] !== '' ? (int)$m[4] : 0,
            isset($m[5]) && $m[5] !== '' ? (int)$m[5] : 0
        );
    }

    return null;
}

/**
 * Legge la vera data di INIZIO dell'evento.
 * Il campo esplicito del feed (es. event_date) ha sempre la precedenza:
 * il testo di titolo e descrizione viene usato solo se il campo manca,
 * perché può citare altre date (es. "dal 13 al 18 agosto").
 * Non considera volutamente pubDate, published o updated.
 */
function parseEventDate(string $explicitDate, string $explicitTime, string $searchableText): ?DateTimeImmutable
{
    $explicitDate = cleanText($explicitDate);
    $explicitTime = cleanText($explicitTime);
    $searchableText = cleanText($searchableText);

    if ($explicitDate !== '') {
        $candidate = trim($explicitDate . ' ' . $explicitTime);

        $date = parseDateFromText($candidate, $explicitTime);
        if ($date) {
            return $date;
        }

        // Ultimo tentativo: formati ISO/RFC riconosciuti direttamente da PHP.
        try {
            return new DateTimeImmutable($candidate, new DateTimeZone('Europe/Rome'));
        } catch (Throwable) {
            // Campo non interpretabile: si prova con il testo dell'evento.
        }
    }

    return parseDateFromText($searchableText, $explicitTime);
}

function eventTimeLabel(DateTimeImmutable $date, string $explicitTime, string $text): string
{
    $explicitTime = cleanText($explicitTime);
    if ($explicitTime !== '') {
        [$hour, $minute] = extractTimeParts($explicitTime);
        if ($hour || $minute || preg_match('/\b0{1,2}[.:]00\b/', $explicitTime)) {
            return sprintf('%02d:%02d', $hour, $minute);
        }
    }

    [$hour, $minute] = extractTimeParts($text);
    if ($hour || $minute || preg_match('/\b0{1,2}[.:]00\b/', $text)) {
        return sprintf('%02d:%02d', $hour, $minute);
    }

    if ($date->format('H:i') !== '00:00') {
        return $date->format('H:i');
    }

    return 'Orario da definire';
}

function parseEvents(string $xmlText, int $limit = MAX_EVENTS): array
{
    if (!function_exists('simplexml_load_string')) {
        throw new RuntimeException('L’estensione PHP SimpleXML non è attiva sul server.');
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($xmlText, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
    if (!$xml) {
        $errors = array_map(
            static fn(LibXMLError $e): string => trim($e->message),
            libxml_get_errors()
        );
        libxml_clear_errors();
        throw new RuntimeException('Feed XML non valido: ' . implode('; ', array_slice($errors, 0, 3)));
    }

    $nodes = $xml->xpath('//*[local-name()="item" or local-name()="entry" or local-name()="evento" or local-name()="event" or local-name()="vevent"]') ?: [];
    if (!$nodes) {
        $nodes = $xml->xpath('/*/*') ?: [];
    }

    $events = [];

    foreach ($nodes as $node) {
        $title = xmlNodeValue($node, ['titolo', 'title', 'nome', 'name', 'summary']);
        $description = xmlNodeValue($node, ['descrizione', 'description', 'excerpt', 'content', 'contentencoded', 'details']);

        // Prima i campi che indicano esplicitamente l'INIZIO dell'evento;
        // event_date per ultimo perché in alcuni feed contiene la data di fine.
        $explicitDate = xmlNodeValue($node, [
            'data_inizio', 'data-inizio', 'datainizio', 'startdate', 'start-date',
            'eventstartdate', 'event_start_date', 'dtstart', 'inizio', 'start',
            'event-date', 'eventdate',
        ]);

        $explicitTime = xmlNodeValue($node, [
            'ora_inizio', 'ora-inizio', 'orainizio', 'starttime', 'start-time',
            'eventtime', 'ora', 'orario',
        ]);

        $searchable = trim($title . ' ' . $description);
        $start = parseEventDate($explicitDate, $explicitTime, $searchable);

        if ($title === '' || !$start) {
            continue;
        }

        $events[] = [
            'title' => $title,
            'description' => $description !== '' ? $description : 'Maggiori informazioni nel calendario online.',
            'category' => xmlNodeValue($node, ['categoria', 'category', 'tipologia', 'type']) ?: 'Evento',
            'place' => xmlNodeValue($node, ['luogo', 'event_location', 'location', 'sede', 'venue', 'address', 'indirizzo']) ?: 'Campoformido',
            'locality' => xmlNodeValue($node, ['frazione', 'localita', 'località', 'city', 'comune', 'town']),
            'start' => $start,
            'time' => eventTimeLabel($start, $explicitTime, $searchable),
            'link' => xmlNodeValue($node, ['link', 'url', 'permalink']),
        ];
    }

    $today = new DateTimeImmutable('today', new DateTimeZone('Europe/Rome'));
    $events = array_values(array_filter(
        $events,
        static fn(array $event): bool => $event['start'] >= $today
    ));

    usort($events, static fn(array $a, array $b): int => $a['start'] <=> $b['start']);

    return array_slice($events, 0, max(0, $limit));
}

function getUpcomingEvents(): array
{
    return parseEvents(fetchFeed(), MAX_EVENTS);
}

function eventHash(array $events): string
{
    $normalized = array_map(static function (array $event): array {
        return [
            'title' => $event['title'],
            'description' => $event['description'],
            'category' => $event['category'],
            'place' => $event['place'],
            'locality' => $event['locality'],
            'start' => $event['start']->format(DateTimeInterface::ATOM),
            'time' => $event['time'],
            'link' => $event['link'],
        ];
    }, $events);

    return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function italianWeekday(DateTimeInterface $date): string
{
    return [
        1 => 'LUNEDÌ', 2 => 'MARTEDÌ', 3 => 'MERCOLEDÌ', 4 => 'GIOVEDÌ',
        5 => 'VENERDÌ', 6 => 'SABATO', 7 => 'DOMENICA',
    ][(int)$date->format('N')];
}

function italianMonth(DateTimeInterface $date): string
{
    return [
        1 => 'GENNAIO', 2 => 'FEBBRAIO', 3 => 'MARZO', 4 => 'APRILE',
        5 => 'MAGGIO', 6 => 'GIUGNO', 7 => 'LUGLIO', 8 => 'AGOSTO',
        9 => 'SETTEMBRE', 10 => 'OTTOBRE', 11 => 'NOVEMBRE', 12 => 'DICEMBRE',
    ][(int)$date->format('n')];
}

function execAvailable(): bool
{
    if (!function_exists('exec')) {
        return false;
    }

    $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));

    return !in_array('exec', $disabled, true);
}

/**
 * Cerca il binario Node: prima il percorso configurato, poi i percorsi
 * tipici dei vari hosting (Plesk, cPanel, ecc.), infine `command -v node`.
 */
function findNodeBinary(): ?string
{
    $candidates = [
        NODE_BINARY,
        '/usr/local/bin/node',
        '/usr/bin/node',
        '/usr/bin/nodejs',
        '/opt/homebrew/bin/node',
    ];

    foreach (['/opt/plesk/node/*/bin/node', '/opt/alt/alt-nodejs*/root/usr/bin/node'] as $pattern) {
        $found = glob($pattern) ?: [];
        rsort($found, SORT_NATURAL);
        $candidates = array_merge($candidates, $found);
    }

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && @is_executable($candidate)) {
            return $candidate;
        }
    }

    if (execAvailable()) {
        $path = trim((string)@exec('command -v node 2>/dev/null'));
        if ($path !== '' && @is_executable($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Tenta la generazione con Playwright/Chromium (resa grafica migliore).
 * Restituisce false se l'ambiente non lo consente o il comando fallisce.
 */
function tryPlaywrightPoster(): bool
{
    if (!execAvailable()) {
        logMessage('Playwright non disponibile: exec() disabilitata sul server.');
        return false;
    }

    if (!is_dir(__DIR__ . '/node_modules/playwright')) {
        logMessage('Playwright non disponibile: node_modules/playwright mancante (eseguire install-playwright.sh).');
        return false;
    }

    $node = findNodeBinary();
    if ($node === null) {
        logMessage('Playwright non disponibile: binario Node non trovato sul server.');
        return false;
    }

    $env = [
        'POSTER_URL' => POSTER_URL,
        'OUTPUT_JPG' => OUTPUT_JPG,
        'JPG_QUALITY' => (string)JPG_QUALITY,
        'DEVICE_SCALE_FACTOR' => (string)POSTER_SCALE,
    ];

    $command = '';
    foreach ($env as $key => $value) {
        $command .= $key . '=' . escapeshellarg($value) . ' ';
    }

    $command .= escapeshellcmd($node)
        . ' '
        . escapeshellarg(__DIR__ . '/generate-image.mjs')
        . ' 2>&1';

    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file(OUTPUT_JPG)) {
        logMessage("Playwright fallito (exit {$exitCode}): " . implode(' | ', array_slice($output, -3)));
        return false;
    }

    return true;
}

/**
 * Genera il JPG master: prova Playwright/Chromium e, se non disponibile
 * (hosting senza Node), ripiega automaticamente sul renderer PHP GD.
 */
function generatePoster(array $events): void
{
    if (tryPlaywrightPoster()) {
        logMessage('JPG master generato con Playwright/Chromium.');
        return;
    }

    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        throw new RuntimeException(
            'Nessun motore grafico disponibile: Playwright/Node non è utilizzabile '
            . 'e l’estensione PHP GD con FreeType non è attiva sul server. '
            . 'Attivare GD dal pannello dell’hosting (di solito in "Impostazioni PHP") '
            . 'oppure installare Node e Playwright. Vedere test.php per la diagnostica.'
        );
    }

    require_once __DIR__ . '/image-renderer.php';
    generatePosterJpg($events, OUTPUT_JPG);
    logMessage('JPG master generato con il renderer PHP GD (fallback).');
}

/**
 * Ricava dal JPG master la variante ottimizzata per il canale WhatsApp:
 * lato maggiore entro WHATSAPP_MAX_SIDE, JPEG baseline e peso contenuto,
 * così WhatsApp non ricomprime l'immagine degradandola.
 */
function generateWhatsAppJpg(string $sourcePath = OUTPUT_JPG, string $targetPath = OUTPUT_JPG_WHATSAPP): void
{
    if (!extension_loaded('gd')) {
        throw new RuntimeException('L’estensione PHP GD non è attiva: impossibile creare il JPG per WhatsApp.');
    }

    if (!is_file($sourcePath)) {
        throw new RuntimeException('JPG master non trovato: ' . $sourcePath);
    }

    $source = @imagecreatefromjpeg($sourcePath);
    if (!$source) {
        throw new RuntimeException('JPG master non leggibile: ' . $sourcePath);
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $longestSide = max($width, $height);

    if ($longestSide > WHATSAPP_MAX_SIDE) {
        $ratio = WHATSAPP_MAX_SIDE / $longestSide;
        $newWidth = max(1, (int)round($width * $ratio));
        $newHeight = max(1, (int)round($height * $ratio));

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($source);
        $source = $resized;
    }

    // JPEG baseline (non progressivo): il formato più compatibile con WhatsApp.
    imageinterlace($source, false);

    $targetDir = dirname($targetPath);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0775, true);
    }

    $tempPath = $targetPath . '.tmp';
    $quality = WHATSAPP_JPG_QUALITY;

    // Riduce gradualmente la qualità solo se il file supera il peso massimo.
    do {
        if (!imagejpeg($source, $tempPath, $quality)) {
            imagedestroy($source);
            @unlink($tempPath);
            throw new RuntimeException('Impossibile scrivere il JPG per WhatsApp: ' . $targetPath);
        }

        clearstatcache(true, $tempPath);
        $sizeOk = filesize($tempPath) <= WHATSAPP_MAX_BYTES;
        $quality -= 5;
    } while (!$sizeOk && $quality >= 60);

    imagedestroy($source);

    if (!rename($tempPath, $targetPath)) {
        @unlink($tempPath);
        throw new RuntimeException('Impossibile salvare il JPG per WhatsApp: ' . $targetPath);
    }
}

function logMessage(string $message): void
{
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0775, true);
    }

    @file_put_contents(
        LOG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}
