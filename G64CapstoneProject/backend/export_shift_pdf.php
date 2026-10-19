<?php
/**
 * Supervisor Shift Yield PDF Export
 * - Reads the same source tables as backend/get_shift_yield_data.php.
 * - Creates a downloadable multi-page PDF without Composer, CDNs, or extra PHP extensions.
 * - Keeps authentication and role authorization on the server.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    exit('GET required.');
}

date_default_timezone_set('Asia/Manila');

/** A small, fixed-layout PDF writer. Uses built-in PDF fonts and WinAnsi encoding. */
final class ShiftYieldPdf
{
    private const W = 842.0; // A4 landscape, points
    private const H = 595.0;
    private array $pages = [];
    private int $page = -1;
    private float $y = 0;
    private string $title;
    private string $generated;

    public function __construct(string $title, string $generated)
    {
        $this->title = $title;
        $this->generated = $generated;
        $this->newPage();
    }

    private function op(string $instructions): void
    {
        $this->pages[$this->page] .= $instructions . "\n";
    }

    private static function enc(string $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $value) ?? '';
        $text = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($text === false) $text = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function fill(float $x, float $top, float $width, float $height, array $color): void
    {
        [$r, $g, $b] = $color;
        $this->op(sprintf('%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f', $r / 255, $g / 255, $b / 255, $x, self::H - $top - $height, $width, $height));
    }

    private function text(float $x, float $top, string $value, int $size = 9, bool $bold = false, array $color = [28, 39, 57]): void
    {
        [$r, $g, $b] = $color;
        $this->op(sprintf('BT /%s %d Tf %.3F %.3F %.3F rg 1 0 0 1 %.2F %.2F Tm (%s) Tj ET',
            $bold ? 'F2' : 'F1', $size, $r / 255, $g / 255, $b / 255,
            $x, self::H - $top, self::enc($value)
        ));
    }

    private function line(float $x, float $top, float $toX, float $toTop): void
    {
        $this->op(sprintf('0.82 0.86 0.91 RG 0.5 w %.2F %.2F m %.2F %.2F l S', $x, self::H - $top, $toX, self::H - $toTop));
    }

    private function newPage(): void
    {
        $this->page++;
        $this->pages[$this->page] = '';
        $this->fill(0, 0, self::W, 10, [43, 105, 83]);
        $this->text(38, 32, 'MASTERS VINEYARD ENTERPRISE', 12, true, [25, 57, 49]);
        $this->text(38, 50, $this->title, 17, true, [22, 37, 55]);
        $this->text(38, 66, 'Generated: ' . $this->generated . ' (Asia/Manila)', 8, false, [94, 108, 125]);
        $this->line(38, 77, self::W - 38, 77);
        $this->y = 95;
    }

    private function reserve(float $height): void
    {
        if ($this->y + $height > self::H - 48) $this->newPage();
    }

    public function section(string $name): void
    {
        $this->reserve(32);
        $this->fill(38, $this->y, self::W - 76, 25, [235, 243, 240]);
        $this->text(48, $this->y + 17, strtoupper($name), 10, true, [25, 83, 68]);
        $this->y += 33;
    }

    /** Split an arbitrary string into short visual lines. Preserve full notes rather than silently truncating. */
    private static function wrap(string $text, float $available, int $fontSize = 8): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($text === '') return ['-'];
        $limit = max(6, (int)floor($available / ($fontSize * 0.52)));
        $words = preg_split('/\s+/u', $text) ?: [$text];
        $out = [];
        $line = '';
        foreach ($words as $word) {
            $segments = str_split($word, $limit); // ASCII-safe enough for report labels; PDF font transliterates later
            foreach ($segments as $segmentIndex => $segment) {
                $candidate = ($line === '') ? $segment : ($segmentIndex === 0 ? $line . ' ' . $segment : $line . $segment);
                if (strlen($candidate) > $limit && $line !== '') {
                    $out[] = $line;
                    $line = $segment;
                } else $line = $candidate;
            }
        }
        if ($line !== '') $out[] = $line;
        return $out ?: ['-'];
    }

    /** @param array<int,array{label:string,width:float}> $columns @param array<int,array<int,string>> $rows */
    public function table(array $columns, array $rows): void
    {
        $this->reserve(32);
        $drawHead = function () use ($columns): void {
            $this->fill(38, $this->y, self::W - 76, 24, [31, 55, 75]);
            $x = 44;
            foreach ($columns as $column) {
                $this->text($x + 1, $this->y + 16, $column['label'], 8, true, [255, 255, 255]);
                $x += $column['width'];
            }
            $this->y += 24;
        };
        $drawHead();
        if (!$rows) {
            $this->reserve(27);
            $this->text(46, $this->y + 16, 'No data available.', 9, false, [105, 114, 130]);
            $this->y += 28;
            return;
        }
        foreach ($rows as $i => $row) {
            $wrapped = [];
            $height = 25.0;
            foreach ($columns as $j => $col) {
                $wrapped[$j] = self::wrap((string)($row[$j] ?? ''), $col['width'] - 9, 8);
                $height = max($height, count($wrapped[$j]) * 11 + 12);
            }
            if ($this->y + $height > self::H - 48) {
                $this->newPage();
                $drawHead();
            }
            if ($i % 2 === 0) $this->fill(38, $this->y, self::W - 76, $height, [244, 247, 249]);
            $x = 44;
            foreach ($columns as $j => $col) {
                foreach ($wrapped[$j] as $lineIndex => $label) {
                    $this->text($x + 1, $this->y + 15 + $lineIndex * 11, $label, 8, false, [35, 49, 63]);
                }
                $x += $col['width'];
            }
            $this->line(38, $this->y + $height, self::W - 38, $this->y + $height);
            $this->y += $height;
        }
        $this->y += 10;
    }

    public function paragraphs(array $pairs): void
    {
        foreach ($pairs as [$label, $body]) {
            $lines = self::wrap($body, self::W - 190, 9);
            $height = max(27, count($lines) * 13 + 10);
            $this->reserve($height);
            $this->text(46, $this->y + 14, $label, 9, true);
            foreach ($lines as $i => $line) $this->text(174, $this->y + 14 + $i * 13, $line, 9);
            $this->y += $height;
        }
    }

    public function download(string $filename): void
    {
        $pageIds = [];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $id = 5;
        $total = count($this->pages);
        foreach ($this->pages as $n => $contents) {
            $contentId = $id + 1;
            $pageIds[] = $id;
            $contents .= sprintf("BT /F1 8 Tf 0.42 0.48 0.56 rg 1 0 0 1 38 24 Tm (Internal production report) Tj ET\n");
            $contents .= sprintf('BT /F1 8 Tf 0.42 0.48 0.56 rg 1 0 0 1 738 24 Tm (Page %d / %d) Tj ET' . "\n", $n + 1, $total);
            $objects[$id] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::W, self::H, $contentId);
            $objects[$contentId] = '<< /Length ' . strlen($contents) . " >>\nstream\n" . $contents . "endstream";
            $id += 2;
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn($n) => $n . ' 0 R', $pageIds)) . '] /Count ' . $total . ' >>';
        ksort($objects);
        $bytes = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offset = [0 => 0];
        $lastId = max(array_keys($objects));
        for ($i = 1; $i <= $lastId; $i++) {
            $offset[$i] = strlen($bytes);
            $bytes .= "$i 0 obj\n" . $objects[$i] . "\nendobj\n";
        }
        $xref = strlen($bytes);
        $bytes .= "xref\n0 " . ($lastId + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $lastId; $i++) $bytes .= sprintf('%010d 00000 n ' . "\n", $offset[$i]);
        $bytes .= "trailer\n<< /Size " . ($lastId + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo $bytes;
    }
}

try {
    // These fields match the shift records returned by the current get_shift_yield_data.php.
    $records = db_all($conn, "SELECT sr.id, sr.session_id, sl.session_code, sl.product_name,
        sl.opened_by, sl.started_at, sl.closed_at, sl.paused_seconds,
        sr.shift_title, sr.shift_date, sr.time_range, sr.produced_units, sr.good_units,
        sr.ng_units, sr.rejected_units, sr.pending_units, sr.line_target,
        sr.closeout_comment, sr.planned_minutes, sr.run_minutes, sr.yield_pct, sr.status
        FROM shift_records sr
        LEFT JOIN supervisor_line_sessions sl ON sl.id = sr.session_id
        ORDER BY sr.shift_date DESC, sr.id DESC");

    // Match the current CSV's second group: workstations for the active shift only.
    $active = db_one($conn, "SELECT * FROM shift_records WHERE status='OPEN' ORDER BY shift_date DESC, id DESC LIMIT 1");
    $stations = $active ? db_all($conn,
        'SELECT station_code, workstation_name, output_units, ng_units, pending_units, yield_pct FROM shift_workstation_yield WHERE shift_record_id = ? ORDER BY id',
        'i', [(int)$active['id']]) : [];

    $now = date('M d, Y h:i A');
    $pdf = new ShiftYieldPdf('SHIFT YIELD REPORT', $now);
    $pdf->section('Shift Records | ' . count($records) . ' records');
    $columns = [
        ['label'=>'SESSION ID', 'width'=>113.0],
        ['label'=>'SHIFT / DATE', 'width'=>135.0],
        ['label'=>'STATUS', 'width'=>56.0],
        ['label'=>'GOOD', 'width'=>48.0],
        ['label'=>'REJECT', 'width'=>51.0],
        ['label'=>'PENDING', 'width'=>55.0],
        ['label'=>'YIELD', 'width'=>55.0],
        ['label'=>'TARGET', 'width'=>52.0],
        ['label'=>'PRODUCT', 'width'=>201.0],
    ];
    $rows = [];
    foreach ($records as $r) {
        $rows[] = [
            (string)($r['session_code'] ?: 'Legacy #' . $r['id']),
            (string)($r['shift_title'] ?? 'Shift') . ' / ' . (string)($r['shift_date'] ?? ''),
            (string)($r['status'] ?? ''),
            (string)($r['good_units'] ?? 0),
            (string)($r['rejected_units'] ?? $r['ng_units'] ?? 0),
            (string)($r['pending_units'] ?? 0),
            number_format((float)($r['yield_pct'] ?? 0), 1) . '%',
            (string)($r['line_target'] ?? 0),
            (string)($r['product_name'] ?: 'Not recorded'),
        ];
    }
    $pdf->table($columns, $rows);

    $pdf->section('Session Timestamps and Supervisor');
    $pdf->table([
        ['label'=>'SESSION ID', 'width'=>135.0],
        ['label'=>'STARTED', 'width'=>160.0],
        ['label'=>'CLOSED', 'width'=>160.0],
        ['label'=>'OPENED BY', 'width'=>180.0],
        ['label'=>'RUN TIME', 'width'=>131.0],
    ], array_map(static function (array $r): array {
        return [
            (string)($r['session_code'] ?: 'Legacy #' . $r['id']),
            (string)($r['started_at'] ?: 'Not recorded'),
            (string)($r['closed_at'] ?: 'In progress'),
            (string)($r['opened_by'] ?: 'Not recorded'),
            isset($r['run_minutes']) ? $r['run_minutes'] . ' min' : 'Not recorded',
        ];
    }, $records));

    $pdf->section('Active Shift Workstation Output');
    if ($active) {
        $pdf->paragraphs([[
            'ACTIVE SHIFT',
            (string)$active['shift_title'] . ' / ' . (string)$active['shift_date'] . ' / ' . (string)$active['status'] . ' / Line target: ' . (string)$active['line_target'],
        ]]);
    }
    $pdf->table([
        ['label'=>'STATION', 'width'=>126.0],
        ['label'=>'WORKSTATION', 'width'=>259.0],
        ['label'=>'GOOD OUTPUT', 'width'=>101.0],
        ['label'=>'REJECTED', 'width'=>94.0],
        ['label'=>'PENDING', 'width'=>94.0],
        ['label'=>'YIELD', 'width'=>92.0],
    ], array_map(static fn(array $w): array => [
        (string)($w['station_code'] ?? ''),
        (string)($w['workstation_name'] ?? ''),
        (string)($w['output_units'] ?? 0),
        (string)($w['ng_units'] ?? 0),
        (string)($w['pending_units'] ?? 0),
        number_format((float)($w['yield_pct'] ?? 0), 1) . '%',
    ], $stations));

    $notes = [];
    foreach ($records as $r) {
        if (trim((string)($r['closeout_comment'] ?? '')) === '') continue;
        $notes[] = [(string)($r['session_code'] ?: 'Legacy #' . $r['id']), (string)$r['closeout_comment']];
    }
    if ($notes) {
        $pdf->section('Closeout Comments');
        $pdf->paragraphs($notes);
    }

    $pdf->download('shift-yield-' . date('Y-m-d-His') . '.pdf');
} catch (Throwable $e) {
    error_log('Shift PDF export failed: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to generate the shift yield PDF report. Check your database connection and server error log.';
}
