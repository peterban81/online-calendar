<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

header('Content-Type: text/html; charset=utf-8');

function checkRow(string $label, bool $ok, string $detail = ''): void
{
    $icon = $ok ? '✅' : '❌';
    $color = $ok ? '#0a7a2f' : '#a00000';
    echo '<tr>';
    echo '<td style="padding:4px 12px 4px 0">' . h($label) . '</td>';
    echo '<td style="padding:4px 12px 4px 0;color:' . $color . '">' . $icon . '</td>';
    echo '<td style="padding:4px 0;color:#555">' . h($detail) . '</td>';
    echo '</tr>';
}

echo '<h1>Test calendario eventi</h1>';

/*
|--------------------------------------------------------------------------
| DIAGNOSTICA MOTORE GRAFICO
|--------------------------------------------------------------------------
*/
echo '<h2>Ambiente di generazione</h2>';
echo '<table style="border-collapse:collapse;font-family:sans-serif;font-size:15px">';

checkRow('Versione PHP', PHP_VERSION_ID >= 80000, PHP_VERSION . (PHP_VERSION_ID < 80000 ? ' — serve PHP 8.0 o superiore' : ''));

$gd = extension_loaded('gd');
$gdInfo = $gd ? gd_info() : [];
checkRow('Estensione GD', $gd, $gd ? (string)($gdInfo['GD Version'] ?? '') : 'non attiva: abilitarla dal pannello hosting');
checkRow('GD: supporto FreeType (testi)', function_exists('imagettftext'), '');
checkRow('GD: supporto JPEG', $gd && !empty($gdInfo['JPEG Support']), '');
checkRow('GD: supporto PNG (logo)', $gd && !empty($gdInfo['PNG Support']), '');

checkRow('Font Inter-Regular.ttf', is_file(FONT_REGULAR_FILE) && is_readable(FONT_REGULAR_FILE), FONT_REGULAR_FILE);
checkRow('Font Inter-Bold.ttf', is_file(FONT_BOLD_FILE) && is_readable(FONT_BOLD_FILE), FONT_BOLD_FILE);
checkRow('Logo comunale', is_file(__DIR__ . '/assets/logo-campoformido.png'), 'assets/logo-campoformido.png');

$outputWritable = is_dir(OUTPUT_DIR) ? is_writable(OUTPUT_DIR) : is_writable(dirname(OUTPUT_DIR));
$dataWritable = is_dir(DATA_DIR) ? is_writable(DATA_DIR) : is_writable(dirname(DATA_DIR));
checkRow('Cartella output/ scrivibile', $outputWritable, OUTPUT_DIR);
checkRow('Cartella data/ scrivibile', $dataWritable, DATA_DIR);

$execOk = execAvailable();
$node = $execOk ? findNodeBinary() : null;
$playwrightReady = is_dir(__DIR__ . '/node_modules/playwright');
checkRow('exec() disponibile (per Playwright)', $execOk, $execOk ? '' : 'disabilitata: verrà usato il renderer GD');
checkRow('Binario Node trovato', $node !== null, $node ?? 'non trovato: verrà usato il renderer GD');
checkRow('Playwright installato (node_modules)', $playwrightReady, $playwrightReady ? '' : 'assente: verrà usato il renderer GD');

echo '</table>';

$gdReady = $gd && function_exists('imagettftext') && !empty($gdInfo['JPEG Support']);
$playwrightUsable = $execOk && $node !== null && $playwrightReady;

if ($playwrightUsable) {
    echo '<p><strong>Motore in uso: Playwright/Chromium</strong> (resa identica al browser).</p>';
} elseif ($gdReady) {
    echo '<p><strong>Motore in uso: PHP GD</strong> (Playwright non disponibile su questo hosting).</p>';
} else {
    echo '<p style="color:#a00000"><strong>Nessun motore grafico utilizzabile.</strong> '
        . 'Attivare l’estensione GD (con FreeType) dal pannello dell’hosting.</p>';
}

echo '<p>'
    . '<a href="index.php">Apri il template HTML</a> · '
    . '<a href="image.php?force=1">Genera il JPG adesso</a> · '
    . '<a href="image.php?whatsapp=1">JPG per WhatsApp</a>'
    . '</p>';

/*
|--------------------------------------------------------------------------
| TEST LETTURA FEED
|--------------------------------------------------------------------------
*/
echo '<h2>Lettura del feed</h2>';

try {
    $events = getUpcomingEvents();

    echo '<p><strong>Eventi trovati:</strong> ' . count($events) . '</p>';

    if (!$events) {
        echo '<p>Nessun evento futuro trovato.</p>';
    }

    foreach ($events as $event) {
        echo '<hr>';
        echo '<h3>' . h($event['title']) . '</h3>';
        echo '<p><strong>Data vera estratta:</strong> ' . h($event['start']->format('d/m/Y H:i')) . '</p>';
        echo '<p><strong>Orario mostrato:</strong> ' . h($event['time']) . '</p>';
        echo '<p><strong>Luogo:</strong> ' . h($event['place']) . '</p>';
        echo '<p>' . h($event['description']) . '</p>';
    }
} catch (Throwable $exception) {
    echo '<pre style="color:#a00">' . h($exception->getMessage()) . '</pre>';
}
