<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$force = isset($_GET['force']) && $_GET['force'] === '1';
$whatsapp = isset($_GET['whatsapp']) && $_GET['whatsapp'] === '1';
$needsGeneration = $force || !is_file(OUTPUT_JPG);

if ($needsGeneration) {
    $node = defined('NODE_BINARY') ? NODE_BINARY : 'node';
    $script = __DIR__ . '/generate-image.mjs';

    $env = [
        'POSTER_URL' => 'https://eventi.impegnopercampoformido.it/calendario-eventi/index.php?render=1',
        'OUTPUT_JPG' => OUTPUT_JPG,
        'JPG_QUALITY' => (string)JPG_QUALITY,
        'DEVICE_SCALE_FACTOR' => (string)POSTER_SCALE,
    ];

    $command = '';
    foreach ($env as $key => $value) {
        $command .= $key . '=' . escapeshellarg($value) . ' ';
    }
    $command .= escapeshellcmd($node) . ' ' . escapeshellarg($script) . ' 2>&1';

    exec($command, $output, $exitCode);

    if ($exitCode !== 0 || !is_file(OUTPUT_JPG)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Impossibile generare il JPG con Playwright.\n\n";
        echo implode("\n", $output);
        exit;
    }
}

$fileToServe = OUTPUT_JPG;

if ($whatsapp) {
    if ($force || !is_file(OUTPUT_JPG_WHATSAPP) || filemtime(OUTPUT_JPG_WHATSAPP) < filemtime(OUTPUT_JPG)) {
        try {
            generateWhatsAppJpg();
        } catch (Throwable $exception) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Impossibile generare il JPG per WhatsApp.\n\n" . $exception->getMessage();
            exit;
        }
    }
    $fileToServe = OUTPUT_JPG_WHATSAPP;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . filesize($fileToServe));
header('Content-Disposition: inline; filename="' . basename($fileToServe) . '"');
readfile($fileToServe);
