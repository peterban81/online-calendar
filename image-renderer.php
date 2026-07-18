<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function scaleValue(int|float $value): int
{
    return (int)round($value * POSTER_SCALE);
}

function findFont(bool $bold = false): string
{
    $configured = $bold ? FONT_BOLD_FILE : FONT_REGULAR_FILE;

    if (is_file($configured) && is_readable($configured)) {
        return $configured;
    }

    $regular = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSansCondensed.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/Library/Fonts/Arial.ttf',
        'C:\Windows\Fonts\arial.ttf',
    ];

    $boldFonts = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSansCondensed-Bold.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
        'C:\Windows\Fonts\arialbd.ttf',
    ];

    foreach ($bold ? $boldFonts : $regular as $path) {
        if (is_file($path) && is_readable($path)) {
            return $path;
        }
    }

    throw new RuntimeException(
        'Nessun font TrueType trovato. Inserire Inter-Regular.ttf e Inter-Bold.ttf in assets/fonts.'
    );
}

function hexColor(GdImage $image, string $hex): int
{
    $hex = ltrim($hex, '#');

    return imagecolorallocate(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2))
    );
}

function roundedRectangle(
    GdImage $image,
    int $x1,
    int $y1,
    int $x2,
    int $y2,
    int $radius,
    int $color
): void {
    imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
}

function textWidth(string $text, float $size, string $font): int
{
    $box = imagettfbbox($size, 0, $font, $text);

    return abs($box[2] - $box[0]);
}

function fitTextSize(
    string $text,
    float $startSize,
    float $minSize,
    int $maxWidth,
    string $font
): float {
    for ($size = $startSize; $size >= $minSize; $size -= 1) {
        if (textWidth($text, $size, $font) <= $maxWidth) {
            return $size;
        }
    }

    return $minSize;
}

function wrapText(
    string $text,
    float $size,
    string $font,
    int $maxWidth,
    int $maxLines
): array {
    $words = preg_split('/\s+/u', trim($text)) ?: [];
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;

        if (textWidth($candidate, $size, $font) <= $maxWidth) {
            $line = $candidate;
            continue;
        }

        if ($line !== '') {
            $lines[] = $line;
        }

        $line = $word;

        if (count($lines) >= $maxLines) {
            break;
        }
    }

    if (count($lines) < $maxLines && $line !== '') {
        $lines[] = $line;
    }

    $lines = array_slice($lines, 0, $maxLines);
    $visible = implode(' ', $lines);

    if ($lines && mb_strlen($visible, 'UTF-8') < mb_strlen($text, 'UTF-8')) {
        $last = array_pop($lines);

        while ($last !== '' && textWidth($last . '…', $size, $font) > $maxWidth) {
            $last = mb_substr($last, 0, -1, 'UTF-8');
        }

        $lines[] = rtrim($last) . '…';
    }

    return $lines;
}

function drawLines(
    GdImage $image,
    array $lines,
    int $x,
    int $y,
    float $size,
    int $color,
    string $font,
    int $lineHeight
): int {
    foreach ($lines as $line) {
        imagettftext($image, $size, 0, $x, $y, $color, $font, $line);
        $y += $lineHeight;
    }

    return $y;
}

function placeTransparentLogo(
    GdImage $canvas,
    string $path,
    int $x,
    int $y,
    int $maxWidth,
    int $maxHeight
): void {
    if (!is_file($path)) {
        return;
    }

    $logo = imagecreatefrompng($path);
    if (!$logo) {
        return;
    }

    imagealphablending($logo, true);
    imagesavealpha($logo, true);

    $ratio = min($maxWidth / imagesx($logo), $maxHeight / imagesy($logo));
    $width = (int)round(imagesx($logo) * $ratio);
    $height = (int)round(imagesy($logo) * $ratio);

    imagecopyresampled(
        $canvas,
        $logo,
        $x + intdiv($maxWidth - $width, 2),
        $y + intdiv($maxHeight - $height, 2),
        0,
        0,
        $width,
        $height,
        imagesx($logo),
        imagesy($logo)
    );

    imagedestroy($logo);
}

function posterHeightForCount(int $count): int
{
    return match (true) {
        $count <= 1 => 720,
        $count === 2 => 900,
        $count === 3 => 1080,
        default => 1350,
    };
}

function drawCalendarIcon(GdImage $image, int $x, int $y, int $white, int $navy): void
{
    imagesetthickness($image, scaleValue(5));

    roundedRectangle(
        $image,
        $x,
        $y + scaleValue(12),
        $x + scaleValue(88),
        $y + scaleValue(98),
        scaleValue(12),
        $white
    );

    imagefilledrectangle(
        $image,
        $x + scaleValue(8),
        $y + scaleValue(34),
        $x + scaleValue(80),
        $y + scaleValue(90),
        $navy
    );

    imageline($image, $x + scaleValue(23), $y, $x + scaleValue(23), $y + scaleValue(27), $white);
    imageline($image, $x + scaleValue(65), $y, $x + scaleValue(65), $y + scaleValue(27), $white);

    foreach ([22, 44, 66] as $cx) {
        foreach ([52, 74] as $cy) {
            imagefilledrectangle(
                $image,
                $x + scaleValue($cx - 5),
                $y + scaleValue($cy - 3),
                $x + scaleValue($cx + 5),
                $y + scaleValue($cy + 3),
                $white
            );
        }
    }

    imagesetthickness($image, 1);
}

function drawClockIcon(GdImage $image, int $x, int $y, int $color): void
{
    imagesetthickness($image, scaleValue(3));
    imageellipse($image, $x, $y, scaleValue(22), scaleValue(22), $color);
    imageline($image, $x, $y, $x, $y - scaleValue(7), $color);
    imageline($image, $x, $y, $x + scaleValue(6), $y, $color);
    imagesetthickness($image, 1);
}

function drawLocationPin(GdImage $image, int $x, int $y, int $color, int $white): void
{
    imagefilledellipse($image, $x, $y - scaleValue(4), scaleValue(20), scaleValue(20), $color);
    imagefilledpolygon(
        $image,
        [
            $x - scaleValue(7), $y + scaleValue(2),
            $x + scaleValue(7), $y + scaleValue(2),
            $x, $y + scaleValue(14),
        ],
        $color
    );
    imagefilledellipse($image, $x, $y - scaleValue(4), scaleValue(7), scaleValue(7), $white);
}

function generatePosterJpg(array $events, string $outputPath): void
{
    if (!extension_loaded('gd') || !function_exists('imagettftext')) {
        throw new RuntimeException('Per generare il JPG servono PHP GD e FreeType.');
    }

    if (!$events) {
        throw new RuntimeException('Nessun evento futuro disponibile.');
    }

    $font = findFont(false);
    $bold = findFont(true);

    $baseWidth = 1080;
    $baseHeight = posterHeightForCount(count($events));
    $width = scaleValue($baseWidth);
    $height = scaleValue($baseHeight);
    $count = count($events);

    $image = imagecreatetruecolor($width, $height);
    imageantialias($image, true);

    $white = hexColor($image, '#FFFFFF');
    $navy = hexColor($image, '#073764');
    $navyDark = hexColor($image, '#052C55');
    $blue = hexColor($image, '#0B5FA9');
    $blueLight = hexColor($image, '#DDEEFF');
    $dateBlue = hexColor($image, '#EAF5FF');
    $text = hexColor($image, '#102D50');
    $muted = hexColor($image, '#637187');
    $line = hexColor($image, '#D6E2ED');
    $shadow = hexColor($image, '#E8EEF4');
    $accent = hexColor($image, '#7CC8EE');

    imagefill($image, 0, 0, $white);

    // Header
    $headerHeight = scaleValue(280);
    imagefilledrectangle($image, 0, 0, $width, $headerHeight, $navyDark);
    imagefilledrectangle(
        $image,
        0,
        $headerHeight - scaleValue(4),
        $width,
        $headerHeight,
        $accent
    );

    // Logo più piccolo
    placeTransparentLogo(
        $image,
        __DIR__ . '/assets/logo-campoformido.png',
        scaleValue(58),
        scaleValue(55),
        scaleValue(108),
        scaleValue(145)
    );

    // Scritta Comune
    imagettftext($image, scaleValue(19), 0, scaleValue(195), scaleValue(105), $white, $font, 'CITTÀ DI');
    imagettftext($image, scaleValue(24), 0, scaleValue(195), scaleValue(145), $white, $bold, 'CAMPOFORMIDO');

    // Il blocco titolo parte dopo la scritta del Comune, senza sovrapporsi
    // né alla scritta né all'icona calendario a destra.
    $brandEnd = scaleValue(195) + textWidth('CAMPOFORMIDO', scaleValue(24), $bold);
    $headlineX = max(scaleValue(475), $brandEnd + scaleValue(35));
    $headlineMaxWidth = scaleValue(900) - $headlineX;

    imagettftext($image, scaleValue(30), 0, $headlineX, scaleValue(82), $white, $bold, 'I PROSSIMI');

    $headline = $count . ' ' . ($count === 1 ? 'EVENTO' : 'EVENTI');
    $headlineSize = fitTextSize($headline, scaleValue(58), scaleValue(36), $headlineMaxWidth, $bold);

    imagettftext($image, $headlineSize, 0, $headlineX, scaleValue(161), $white, $bold, $headline);

    imagettftext(
        $image,
        scaleValue(29),
        0,
        $headlineX,
        scaleValue(216),
        hexColor($image, '#DDEFFF'),
        $bold,
        'da non perdere'
    );

    drawCalendarIcon($image, scaleValue(920), scaleValue(62), $white, $navyDark);

    // Eventi
    $eventsTop = 310;
    $footerHeight = 100;
    $footerBottomMargin = 28;
    $footerTop = $baseHeight - $footerHeight - $footerBottomMargin;
    $gap = 18;

    $availableHeight = $footerTop - $eventsTop - 18;
    $cardHeight = (int)floor(($availableHeight - (($count - 1) * $gap)) / $count);
    $cardHeight = min(220, max(150, $cardHeight));

    // Le coordinate verticali interne sono progettate per card alte 175 px:
    // per card più basse vengono compresse in proporzione.
    $s = min(1.0, $cardHeight / 175);
    $sy = static fn(int|float $value): int => (int)round($value * $s);

    $y = $eventsTop;

    foreach ($events as $event) {
        $x1 = 42;
        $x2 = 1038;

        roundedRectangle(
            $image,
            scaleValue($x1),
            scaleValue($y + 6),
            scaleValue($x2),
            scaleValue($y + $cardHeight + 6),
            scaleValue(18),
            $shadow
        );

        roundedRectangle(
            $image,
            scaleValue($x1),
            scaleValue($y),
            scaleValue($x2),
            scaleValue($y + $cardHeight),
            scaleValue(18),
            $line
        );

        roundedRectangle(
            $image,
            scaleValue($x1 + 2),
            scaleValue($y + 2),
            scaleValue($x2 - 2),
            scaleValue($y + $cardHeight - 2),
            scaleValue(17),
            $white
        );

        $dateRight = 225;

        // Fondino azzurro con gli angoli sinistri stondati come la card
        roundedRectangle(
            $image,
            scaleValue($x1 + 2),
            scaleValue($y + 2),
            scaleValue($dateRight),
            scaleValue($y + $cardHeight - 2),
            scaleValue(17),
            $dateBlue
        );

        // Il lato destro del fondino resta dritto fino alla linea divisoria
        imagefilledrectangle(
            $image,
            scaleValue($dateRight - 20),
            scaleValue($y + 2),
            scaleValue($dateRight),
            scaleValue($y + $cardHeight - 2),
            $dateBlue
        );

        // Fascia blu verticale con estremi arrotondati, contenuta
        // dentro gli angoli stondati della card.
        roundedRectangle(
            $image,
            scaleValue($x1 + 2),
            scaleValue($y + 14),
            scaleValue($x1 + 8),
            scaleValue($y + $cardHeight - 14),
            scaleValue(3),
            $blue
        );

        imageline(
            $image,
            scaleValue($dateRight),
            scaleValue($y + 2),
            scaleValue($dateRight),
            scaleValue($y + $cardHeight - 2),
            $line
        );

        $weekday = italianWeekday($event['start']);
        $weekdaySize = fitTextSize(
            $weekday,
            scaleValue(18),
            scaleValue(13),
            scaleValue(145),
            $bold
        );

        imagettftext(
            $image,
            $weekdaySize,
            0,
            scaleValue(134) - intdiv(textWidth($weekday, $weekdaySize, $bold), 2),
            scaleValue($y + $sy(57)),
            $navy,
            $bold,
            $weekday
        );

        $day = $event['start']->format('j');
        $daySize = scaleValue(59);

        imagettftext(
            $image,
            $daySize,
            0,
            scaleValue(134) - intdiv(textWidth($day, $daySize, $bold), 2),
            scaleValue($y + $sy(132)),
            $navy,
            $bold,
            $day
        );

        $month = italianMonth($event['start']);
        $monthSize = fitTextSize(
            $month,
            scaleValue(17),
            scaleValue(12),
            scaleValue(145),
            $bold
        );

        imagettftext(
            $image,
            $monthSize,
            0,
            scaleValue(134) - intdiv(textWidth($month, $monthSize, $bold), 2),
            scaleValue($y + $sy(167)),
            $navy,
            $bold,
            $month
        );

        $mainX = 258;
        $metaDividerX = 765;
        $mainWidth = $metaDividerX - $mainX - 32;

        roundedRectangle(
            $image,
            scaleValue($mainX),
            scaleValue($y + 24),
            scaleValue($mainX + 96),
            scaleValue($y + 55),
            scaleValue(8),
            $blueLight
        );

        $category = mb_strtoupper($event['category'], 'UTF-8');
        $categorySize = fitTextSize(
            $category,
            scaleValue(13),
            scaleValue(10),
            scaleValue(76),
            $bold
        );

        imagettftext(
            $image,
            $categorySize,
            0,
            scaleValue($mainX + 11),
            scaleValue($y + 46),
            $navy,
            $bold,
            $category
        );

        // Le dimensioni GD sono in punti (1 pt ≈ 1,33 px): l'interlinea
        // deve essere ~1,5 volte il corpo per evitare sovrapposizioni.
        $titleBaseSize = $count <= 2 ? 24 : 21;
        $titleSize = scaleValue($titleBaseSize);
        $titleLineHeight = scaleValue((int)round($titleBaseSize * 1.55));
        $titleLines = wrapText(
            $event['title'],
            $titleSize,
            $bold,
            scaleValue($mainWidth),
            2
        );

        $afterTitle = drawLines(
            $image,
            $titleLines,
            scaleValue($mainX),
            scaleValue($y + $sy(90)),
            $titleSize,
            $text,
            $bold,
            $titleLineHeight
        );

        $descriptionBaseSize = $count <= 2 ? 16 : 14;
        $descriptionSize = scaleValue($descriptionBaseSize);
        $descriptionLineHeight = scaleValue((int)round($descriptionBaseSize * 1.5));
        $descriptionStart = $afterTitle - scaleValue(2);

        // Solo le righe che restano dentro la card, senza toccare il bordo.
        $maxDescriptionLines = 0;
        $bottomLimit = scaleValue($y + $cardHeight - 12);
        for ($n = 1; $n <= 2; $n++) {
            if ($descriptionStart + ($n - 1) * $descriptionLineHeight <= $bottomLimit) {
                $maxDescriptionLines = $n;
            }
        }

        if ($maxDescriptionLines > 0) {
            $descriptionLines = wrapText(
                $event['description'],
                $descriptionSize,
                $font,
                scaleValue($mainWidth),
                $maxDescriptionLines
            );

            drawLines(
                $image,
                $descriptionLines,
                scaleValue($mainX),
                $descriptionStart,
                $descriptionSize,
                $muted,
                $font,
                $descriptionLineHeight
            );
        }

        imageline(
            $image,
            scaleValue($metaDividerX),
            scaleValue($y + 34),
            scaleValue($metaDividerX),
            scaleValue($y + $cardHeight - 34),
            $line
        );

        $metaX = 808;

        // L'ora d'inizio: dimensione ridotta per orari lunghi tipo "Orario da definire"
        $timeSize = fitTextSize(
            $event['time'],
            scaleValue(31),
            scaleValue(15),
            scaleValue(180),
            $bold
        );

        drawClockIcon($image, scaleValue($metaX), scaleValue($y + $sy(76)), $blue);

        imagettftext(
            $image,
            $timeSize,
            0,
            scaleValue($metaX + 35),
            scaleValue($y + $sy(87)),
            $text,
            $bold,
            $event['time']
        );

        drawLocationPin(
            $image,
            scaleValue($metaX),
            scaleValue($y + $sy(137)),
            $blue,
            $white
        );

        $placeSize = fitTextSize(
            $event['place'],
            scaleValue(17),
            scaleValue(13),
            scaleValue(175),
            $bold
        );

        imagettftext(
            $image,
            $placeSize,
            0,
            scaleValue($metaX + 35),
            scaleValue($y + $sy(144)),
            $text,
            $bold,
            $event['place']
        );

        if ($event['locality'] !== '') {
            imagettftext(
                $image,
                scaleValue(13),
                0,
                scaleValue($metaX + 35),
                scaleValue($y + $sy(167)),
                $muted,
                $font,
                $event['locality']
            );
        }

        $y += $cardHeight + $gap;
    }

    // Footer
    roundedRectangle(
        $image,
        scaleValue(42),
        scaleValue($footerTop),
        scaleValue(1038),
        scaleValue($footerTop + $footerHeight),
        scaleValue(16),
        $blueLight
    );

    imagettftext(
        $image,
        scaleValue(14),
        0,
        scaleValue(68),
        scaleValue($footerTop + 34),
        $navy,
        $bold,
        'CALENDARIO COMPLETO'
    );

    imagettftext(
        $image,
        scaleValue(20),
        0,
        scaleValue(68),
        scaleValue($footerTop + 65),
        $navy,
        $bold,
        'www.comune.campoformido.ud.it'
    );

    imageline(
        $image,
        scaleValue(855),
        scaleValue($footerTop + 22),
        scaleValue(855),
        scaleValue($footerTop + 77),
        hexColor($image, '#8AA1B8')
    );

    imagettftext(
        $image,
        scaleValue(13),
        0,
        scaleValue(884),
        scaleValue($footerTop + 36),
        $muted,
        $font,
        'Aggiornato al'
    );

    imagettftext(
        $image,
        scaleValue(16),
        0,
        scaleValue(884),
        scaleValue($footerTop + 65),
        $text,
        $bold,
        date('d/m/Y')
    );

    if (!is_dir(dirname($outputPath))) {
        mkdir(dirname($outputPath), 0775, true);
    }

    if (!imagejpeg($image, $outputPath, JPG_QUALITY)) {
        imagedestroy($image);
        throw new RuntimeException('Impossibile salvare il JPG.');
    }

    imagedestroy($image);
}
