<?php
require_once __DIR__ . '/../assets/php/layout.php';

$period = (string)($_GET['period'] ?? 'week');
if (!in_array($period, ['week', 'month', 'year'], true)) $period = 'week';
$span = $period === 'week' ? '7 DAY' : ($period === 'month' ? '30 DAY' : '365 DAY');
$rows = [];
$dailyRows = [];
$reportStats = null;
$error = '';

try {
    $pdo = db();
    $device = get_active_device($pdo);
    if ($device) {
        $stmt = $pdo->prepare("SELECT COUNT(*) AS total, COUNT(DISTINCT DATE(measured_at)) AS days, MIN(DATE(measured_at)) AS first_day, MAX(DATE(measured_at)) AS last_day FROM readings WHERE device_id=? AND measured_at >= UTC_TIMESTAMP() - INTERVAL {$span}");
        $stmt->execute([$device['id']]);
        $reportStats = $stmt->fetch();
        $stmt = $pdo->prepare("SELECT measured_at,temperature_c,humidity_pct,air_adc,gas_digital,light_adc FROM readings WHERE device_id=? AND measured_at >= UTC_TIMESTAMP() - INTERVAL {$span} ORDER BY measured_at DESC LIMIT 10");
        $stmt->execute([$device['id']]);
        $rows = $stmt->fetchAll();
        $stmt = $pdo->prepare("SELECT DATE(measured_at) AS day, COUNT(*) AS readings, ROUND(AVG(temperature_c),1) AS avg_temp, ROUND(AVG(humidity_pct),1) AS avg_humidity, MIN(temperature_c) AS min_temp, MAX(temperature_c) AS max_temp FROM readings WHERE device_id=? AND measured_at >= UTC_TIMESTAMP() - INTERVAL {$span} GROUP BY DATE(measured_at) ORDER BY day DESC");
        $stmt->execute([$device['id']]);
        $dailyRows = $stmt->fetchAll();
    }
} catch (Throwable $exception) {
    $error = database_error_message();
}

if (($_GET['export'] ?? '') === 'docx' && !$error) {
    if (!class_exists(ZipArchive::class)) {
        http_response_code(503);
        exit('DOCX export requires PHP ZipArchive. Enable the zip extension in XAMPP PHP.');
    }

    $xml = static fn(string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $paragraph = static function (string $text, int $size = 22, bool $bold = false, string $color = '26372B', int $after = 100, string $align = '') use ($xml): string {
        return '<w:p><w:pPr><w:spacing w:after="'.$after.'"/>'.($align ? '<w:jc w:val="'.$align.'"/>' : '').'</w:pPr><w:r><w:rPr><w:color w:val="'.$color.'"/><w:sz w:val="'.$size.'"/>'.($bold ? '<w:b/>' : '').'</w:rPr><w:t xml:space="preserve">'.$xml($text).'</w:t></w:r></w:p>';
    };
    $cellBorders = '<w:tcBorders><w:top w:val="single" w:sz="4" w:color="D5DFD8"/><w:left w:val="single" w:sz="4" w:color="D5DFD8"/><w:bottom w:val="single" w:sz="4" w:color="D5DFD8"/><w:right w:val="single" w:sz="4" w:color="D5DFD8"/></w:tcBorders>';
    $cell = static function (string $text, bool $header, int $width) use ($paragraph, $cellBorders): string {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/>'.$cellBorders.($header ? '<w:shd w:fill="285C3B"/>' : '').'</w:tcPr>'.$paragraph($text, $header ? 21 : 20, $header, $header ? 'FFFFFF' : '26372B', 35).'</w:tc>';
    };
    $makeTable = static function (array $headers, array $data, array $widths) use ($cell): string {
        $grid = implode('', array_map(static fn(int $width): string => '<w:gridCol w:w="'.$width.'"/>', $widths));
        $table = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="D5DFD8"/><w:left w:val="single" w:sz="4" w:color="D5DFD8"/><w:bottom w:val="single" w:sz="4" w:color="D5DFD8"/><w:right w:val="single" w:sz="4" w:color="D5DFD8"/><w:insideH w:val="single" w:sz="4" w:color="D5DFD8"/><w:insideV w:val="single" w:sz="4" w:color="D5DFD8"/></w:tblBorders></w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid><w:tr>';
        foreach ($headers as $index => $value) $table .= $cell($value, true, $widths[$index]);
        $table .= '</w:tr>';
        foreach ($data as $row) {
            $table .= '<w:tr>';
            foreach ($row as $index => $value) $table .= $cell((string)$value, false, $widths[$index]);
            $table .= '</w:tr>';
        }
        return $table.'</w:tbl>';
    };

    $total = (int)($reportStats['total'] ?? 0);
    $daysWithData = (int)($reportStats['days'] ?? 0);
    $range = $total ? $reportStats['first_day'].' to '.$reportStats['last_day'] : 'No readings in this period';
    $generated = date('F j, Y · g:i A').' (Asia/Manila)';
    $periodLabel = ['week' => 'Last 7 days', 'month' => 'Last 30 days', 'year' => 'Last 365 days'][$period];
    $deviceName = (string)($device['name'] ?? 'No device selected');
    $location = (string)($device['location'] ?? '—');
    $readingRows = array_map(static fn(array $row): array => [
        gmdate('Y-m-d H:i', strtotime($row['measured_at'].' UTC')),
        'T: '.($row['temperature_c'] === null ? '—' : $row['temperature_c'].' C').'  |  RH: '.($row['humidity_pct'] === null ? '—' : $row['humidity_pct'].'%').'  |  Air: '.$row['air_adc'].'  |  Gas: '.$row['gas_digital'].'  |  Light: '.$row['light_adc'],
    ], $rows);
    while (count($readingRows) < 10) $readingRows[] = ['—', '—'];
    $metadata = $makeTable(['REPORT DETAIL', 'VALUE'], [
        ['Period', $periodLabel],
        ['Device', $deviceName],
        ['Location', $location],
        ['Generated', $generated],
        ['Coverage (UTC)', $range],
    ], [2450, 4800]);
    $summary = $makeTable(['TOTAL READINGS', 'DAYS WITH DATA'], [[
        (string)$total, (string)$daysWithData,
    ]], [3600, 3650]);
    $dataTable = $makeTable(
        ['Timestamp (UTC)', 'T = C  |  RH = %  |  Air and light = ADC  |  Gas = 0/1'],
        $readingRows,
        [2450, 4800]
    );

    $header = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'.$paragraph('PAMANTASAN NG LUNGSOD NG MUNTINLUPA (PLMUN)', 18, true, '285C3B', 15).$paragraph('CLASSROOM ENVIRONMENT MONITOR  |  ENVIRONMENTAL MONITORING REPORT', 16, false, '66756B', 70).'<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="12" w:color="285C3B"/></w:pBdr></w:pPr></w:p></w:hdr>';
    $footer = '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr><w:pBdr><w:top w:val="single" w:sz="6" w:color="C9D8CD"/></w:pBdr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="285C3B"/><w:b/><w:sz w:val="17"/></w:rPr><w:t>Pamantasan ng Lungsod ng Muntinlupa (PLMUN)</w:t></w:r></w:p><w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:color w:val="66756B"/><w:sz w:val="15"/></w:rPr><w:t>Classroom Environment Monitor  ·  Copyright © '.date('Y').' PLMUN  ·  Generated '.$generated.'</w:t></w:r></w:p></w:ftr>';
    $body = $paragraph('ENVIRONMENTAL DATA REPORT', 20, true, '66756B', 40, 'center')
        .$paragraph('Classroom Monitoring Summary', 40, true, '26372B', 30, 'center')
        .$paragraph($periodLabel, 25, false, '285C3B', 100, 'center')
        .$paragraph('REPORT INFORMATION', 24, true, '285C3B', 40)
        .$metadata
        .$paragraph('SUMMARY', 24, true, '285C3B', 40)
        .$summary
        .$paragraph('LATEST SENSOR READINGS (UP TO 10)', 24, true, '285C3B', 40)
        .$dataTable
        .$paragraph('DATA NOTES', 24, true, '285C3B', 40)
        .$paragraph('The table lists up to 10 of the most recent actual readings within the selected period (timestamps are UTC). Empty rows keep the report layout consistent when fewer than 10 readings are available. T is temperature in Celsius, RH is relative humidity, air and light are raw ADC values, and gas is the digital sensor value. A dash means the value was not recorded.', 20, false, '4B5A50', 45)
        .$paragraph('Prepared automatically on '.$generated.'. Values reflect data available at export time and are intended for classroom monitoring and record keeping.', 19, false, '66756B', 0);
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>'.$body.'<w:sectPr><w:headerReference w:type="default" r:id="rId1"/><w:footerReference w:type="default" r:id="rId2"/><w:pgSz w:w="8391" w:h="11906"/><w:pgMar w:top="1000" w:right="550" w:bottom="950" w:left="550" w:header="450" w:footer="400"/></w:sectPr></w:body></w:document>';
    $relationships = '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>';

    $temp = tempnam(sys_get_temp_dir(), 'env-report-');
    $zip = new ZipArchive();
    if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($temp);
        http_response_code(500);
        exit('Could not create the DOCX report.');
    }
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/><Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/></Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', $relationships);
    $zip->addFromString('word/document.xml', $document);
    $zip->addFromString('word/header1.xml', $header);
    $zip->addFromString('word/footer1.xml', $footer);
    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="environment-report-'.$period.'-'.gmdate('Y-m-d').'.docx"');
    header('Content-Length: '.filesize($temp));
    readfile($temp);
    @unlink($temp);
    exit;
}

layout_start('Reports', 'reports');
?>
<section class="content-card"><div class="section-heading"><div><h2><?= e(ucfirst($period)) ?> report</h2><p>Daily averages from readings received in the selected period.</p></div><div class="report-actions"><nav class="segmented" aria-label="Report period"><?php foreach (['week' => '7 days', 'month' => '30 days', 'year' => '1 year'] as $key => $label): ?><a class="<?= $period === $key ? 'selected' : '' ?>" href="?period=<?= e($key) ?>"><?= e($label) ?></a><?php endforeach; ?></nav><?php if (!$error && $rows): ?><a class="button-primary small" href="?period=<?= e($period) ?>&amp;export=docx">Export DOCX</a><?php endif; ?></div></div>
<?php if ($error): ?><p class="inline-notice"><?= e($error) ?></p><?php elseif (!$dailyRows): ?><p class="empty-state">Reports will be available when the device has uploaded readings.</p><?php else: ?><p class="chart-legend">Coverage: <?= e(min(array_column($dailyRows, 'day'))) ?> to <?= e(max(array_column($dailyRows, 'day'))) ?> UTC · <?= array_sum(array_map(static fn($row) => (int)$row['readings'], $dailyRows)) ?> readings across <?= count($dailyRows) ?> days.</p><div class="table-scroll"><table><thead><tr><th>Date (UTC)</th><th>Readings</th><th>Avg temp</th><th>Min temp</th><th>Max temp</th><th>Avg humidity</th></tr></thead><tbody><?php foreach ($dailyRows as $row): ?><tr><td><?= e($row['day']) ?></td><td><?= (int)$row['readings'] ?></td><td><?= $row['avg_temp'] === null ? '—' : e((string)$row['avg_temp']).' °C' ?></td><td><?= $row['min_temp'] === null ? '—' : e((string)$row['min_temp']).' °C' ?></td><td><?= $row['max_temp'] === null ? '—' : e((string)$row['max_temp']).' °C' ?></td><td><?= $row['avg_humidity'] === null ? '—' : e((string)$row['avg_humidity']).' %' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php layout_end(); ?>
