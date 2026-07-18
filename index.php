<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$error = '';
$events = [];

try {
    $events = getUpcomingEvents();
} catch (Throwable $exception) {
    $error = $exception->getMessage();
}

$count = count($events);
$updatedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('d/m/Y');
?>
<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prossimi eventi – Città di Campoformido</title>
    <link rel="stylesheet" href="styles.css?v=<?= h((string)filemtime(__DIR__ . '/styles.css')) ?>">
</head>
<body class="<?= isset($_GET['render']) ? 'render-mode' : '' ?>">
<main class="poster count-<?= $count ?>" id="poster">
    <header class="hero">
        <div class="brand">
            <img src="assets/logo-campoformido.png" alt="Stemma della Città di Campoformido" class="logo">
            <div class="brand-copy">
                <span>CITTÀ DI</span>
                <strong>CAMPOFORMIDO</strong>
            </div>
        </div>

        <div class="headline">
            <span>I PROSSIMI</span>
            <div class="count-line">
                <strong class="event-count"><?= $count ?></strong>
                <strong>EVENT<?= $count === 1 ? 'O' : 'I' ?></strong>
            </div>
            <em>da non perdere</em>
        </div>

        <svg class="calendar-mark" viewBox="0 0 64 64" aria-hidden="true">
            <rect x="7" y="12" width="50" height="45" rx="6"></rect>
            <path d="M18 7v11M46 7v11M7 25h50"></path>
            <path d="M18 35h6M29 35h6M40 35h6M18 45h6M29 45h6M40 45h6"></path>
        </svg>
    </header>

    <section class="events">
        <?php if ($error !== ''): ?>
            <div class="status error">
                <strong>Non è stato possibile leggere il calendario.</strong>
                <span><?= h($error) ?></span>
            </div>
        <?php elseif (!$events): ?>
            <div class="status">Al momento non risultano eventi futuri nel calendario.</div>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <article class="event-card">
                    <div class="date-block">
                        <span class="weekday"><?= h(italianWeekday($event['start'])) ?></span>
                        <strong class="day"><?= h($event['start']->format('j')) ?></strong>
                        <span class="month"><?= h(italianMonth($event['start'])) ?></span>
                    </div>

                    <div class="event-main">
                        <span class="category"><?= h(mb_strtoupper($event['category'], 'UTF-8')) ?></span>
                        <h2 class="title"><?= h($event['title']) ?></h2>
                        <p class="description"><?= h($event['description']) ?></p>
                    </div>

                    <div class="event-meta">
                        <div class="meta-row">
                            <span class="meta-icon clock" aria-hidden="true"></span>
                            <strong><?= h($event['time']) ?></strong>
                        </div>
                        <div class="meta-row">
                            <span class="meta-icon pin" aria-hidden="true"></span>
                            <div>
                                <strong><?= h($event['place']) ?></strong>
                                <?php if ($event['locality'] !== ''): ?>
                                    <span><?= h($event['locality']) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <footer class="footer">
        <div>
            <span class="footer-label">CALENDARIO COMPLETO</span>
            <a href="<?= h(CALENDAR_URL) ?>" target="_blank" rel="noopener">www.comune.campoformido.ud.it</a>
        </div>
        <div class="updated">
            <span>Aggiornato al</span>
            <strong><?= h($updatedAt) ?></strong>
        </div>
    </footer>
</main>
</body>
</html>
