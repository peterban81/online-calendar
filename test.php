<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h1>Test calendario eventi</h1>';

try {
    $events = getUpcomingEvents();

    echo '<p><strong>Eventi trovati:</strong> ' . count($events) . '</p>';
    echo '<p><a href="index.php">Apri il template</a> · <a href="image.php">Genera/visualizza il JPG</a></p>';

    if (!$events) {
        echo '<p>Nessun evento futuro trovato.</p>';
    }

    foreach ($events as $event) {
        echo '<hr>';
        echo '<h2>' . h($event['title']) . '</h2>';
        echo '<p><strong>Data vera estratta:</strong> ' . h($event['start']->format('d/m/Y H:i')) . '</p>';
        echo '<p><strong>Orario mostrato:</strong> ' . h($event['time']) . '</p>';
        echo '<p><strong>Luogo:</strong> ' . h($event['place']) . '</p>';
        echo '<p>' . h($event['description']) . '</p>';
    }
} catch (Throwable $exception) {
    echo '<pre style="color:#a00">' . h($exception->getMessage()) . '</pre>';
}
