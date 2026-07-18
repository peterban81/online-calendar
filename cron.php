<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $providedKey = (string)($_GET['key'] ?? '');
    if (CRON_SECRET === 'CAMBIA-QUESTA-CHIAVE-PRIMA-DI-USARE-IL-CRON' || !hash_equals(CRON_SECRET, $providedKey)) {
        http_response_code(403);
        exit('Accesso negato.');
    }

    header('Content-Type: text/plain; charset=utf-8');
}

$force = $isCli
    ? in_array('--force', $argv ?? [], true)
    : isset($_GET['force']) && $_GET['force'] === '1';

try {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }
    if (!is_dir(OUTPUT_DIR)) {
        mkdir(OUTPUT_DIR, 0775, true);
    }

    $events = getUpcomingEvents();
    if (!$events) {
        logMessage('Nessun evento futuro. Nessuna generazione.');
        exit("Nessun evento futuro. Nessuna generazione.\n");
    }

    $newHash = eventHash($events);
    $oldHash = is_file(HASH_FILE) ? trim((string)file_get_contents(HASH_FILE)) : '';
    $firstRun = $oldHash === '';

    if (!$force && !$firstRun && hash_equals($oldHash, $newHash)) {
        logMessage('Eventi invariati. Nessuna API, immagine o email utilizzata.');
        exit("Eventi invariati. Nessuna immagine generata e nessuna email inviata.\n");
    }

    if (!$force && $firstRun && SILENT_FIRST_RUN) {
        file_put_contents(HASH_FILE, $newHash, LOCK_EX);
        logMessage('Prima esecuzione silenziosa: hash iniziale salvato.');
        exit("Hash iniziale salvato. Nessuna email inviata.\n");
    }

    generatePoster($events);
    generateWhatsAppJpg();

    $subject = 'Prossimi ' . count($events) . ' eventi a Campoformido';
    $body = "In allegato trovi la nuova grafica con i prossimi eventi.\n\n"
        . "- " . basename(OUTPUT_JPG) . ": versione ad alta risoluzione (stampa, sito).\n"
        . "- " . basename(OUTPUT_JPG_WHATSAPP) . ": versione pronta per il canale WhatsApp del Comune.\n\n"
        . "Generata automaticamente il " . date('d/m/Y \a\l\l\e H:i') . ".\n"
        . CALENDAR_URL;

    sendJpgEmail(
        DESTINATION_EMAILS,
        $subject,
        $body,
        [OUTPUT_JPG, OUTPUT_JPG_WHATSAPP]
    );

    file_put_contents(HASH_FILE, $newHash, LOCK_EX);
    logMessage(
        'Grafica generata e inviata: ' . basename(OUTPUT_JPG)
        . ' + ' . basename(OUTPUT_JPG_WHATSAPP)
        . ' - eventi: ' . count($events)
    );

    echo "Grafica generata e inviata correttamente.\n";
} catch (Throwable $exception) {
    logMessage('ERRORE: ' . $exception->getMessage());
    http_response_code(500);
    echo "Errore: " . $exception->getMessage() . "\n";
    exit(1);
}

function sendJpgEmail(array $recipients, string $subject, string $body, array $attachmentPaths): void
{
    foreach ($attachmentPaths as $attachmentPath) {
        if (!is_file($attachmentPath)) {
            throw new RuntimeException('Allegato JPG non trovato: ' . $attachmentPath);
        }
    }

    if (!$attachmentPaths) {
        throw new RuntimeException('Nessun allegato JPG da inviare.');
    }

    $boundary = '=_Campoformido_' . bin2hex(random_bytes(12));

    $headers = [
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $message = '--' . $boundary . "\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $body . "\r\n\r\n";

    foreach ($attachmentPaths as $attachmentPath) {
        $filename = basename($attachmentPath);
        $attachment = chunk_split(base64_encode((string)file_get_contents($attachmentPath)));

        $message .= '--' . $boundary . "\r\n";
        $message .= 'Content-Type: image/jpeg; name="' . $filename . '"' . "\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n";
        $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
        $message .= $attachment . "\r\n";
    }

    $message .= '--' . $boundary . "--\r\n";

    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

    $recipients = array_values(array_filter(array_map('trim', $recipients)));

    if (!$recipients) {
        throw new RuntimeException('Nessun destinatario email configurato.');
    }

    foreach ($recipients as $recipient) {
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Indirizzo email non valido: ' . $recipient);
        }
    }

    $to = implode(', ', $recipients);

    if (!mail($to, $encodedSubject, $message, implode("\r\n", $headers))) {
        throw new RuntimeException(
            'La funzione PHP mail() non ha accettato il messaggio. Verificare il mittente o configurare SMTP/PHPMailer.'
        );
    }
}
