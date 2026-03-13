<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$pageTitle = 'Import Indikator';

$validLeveling = ['IKSP', 'IKSK-2', 'IKSK-3', 'IKSK-4'];
$levelOrder    = ['IKSP' => 1, 'IKSK-2' => 2, 'IKSK-3' => 3, 'IKSK-4' => 4];

// ─── Konversi kolom Excel A,B,C... ke index 0-based ─────────────────────────
function colToIndex($ref) {
    $col = strtoupper(preg_replace('/[^A-Za-z]/', '', $ref));
    $idx = 0;
    foreach (str_split($col) as $ch) $idx = $idx * 26 + ord($ch) - 64;
    return $idx - 1;
}

// ─── Shared strings ──────────────────────────────────────────────────────────
function getSharedStrings($zip) {
    $ss  = [];
    $idx = $zip->locateName('xl/sharedStrings.xml');
    if ($idx === false) return $ss;
    $xml = @simplexml_load_string($zip->getFromIndex($idx), 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) return $ss;
    foreach ($xml->si as $si) {
        $t = '';
        if (isset($si->t))     $t = (string)$si->t;
        elseif (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
        $ss[] = $t;
    }
    return $ss;
}

// ─── Baca sheet — pakai cell reference agar sel kosong tetap pada posisinya ──
function readSheet($zip, $file, $ss) {
    $content = $zip->getFromName($file);
    if (!$content) return [];
    $xml = @simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) return [];
    $rows = [];
    foreach ($xml->sheetData->row as $rowNode) {
        $rowIdx = (int)($rowNode['r']) - 1;
        $rd = [];
        foreach ($rowNode->c as $cell) {
            $colIdx = colToIndex((string)($cell['r'] ?? 'A1'));
            $type   = (string)($cell['t'] ?? '');
            $val    = (string)($cell->v ?? '');
            if ($type === 's')             $val = $ss[(int)$val] ?? '';
            elseif ($type === 'inlineStr') $val = isset($cell->is->t) ? (string)$cell->is->t : $val;
            while (count($rd) <= $colIdx) $rd[] = '';
            $rd[$colIdx] = trim($val);
        }
        $rows[$rowIdx] = $rd;
    }
    return array_values($rows);
}

// ─── Daftar sheet dari workbook ──────────────────────────────────────────────
function getSheetList($zip) {
    $sheets = [];
    $wbIdx  = $zip->locateName('xl/workbook.xml');
    if ($wbIdx === false) return $sheets;
    $xml = @simplexml_load_string($zip->getFromIndex($wbIdx), 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$xml) return $sheets;
    $rels = [];
    $rIdx = $zip->locateName('xl/_rels/workbook.xml.rels');
    if ($rIdx !== false) {
        $rx = @simplexml_load_string($zip->getFromIndex($rIdx), 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($rx) foreach ($rx->Relationship as $r)
            $rels[(string)$r['Id']] = 'xl/' . ltrim((string)$r['Target'], '/');
    }
    foreach ($xml->sheets->sheet as $s) {
        $attrs = $s->attributes('r', true);
        $rId   = (string)($attrs['id'] ?? '');
        $name  = (string)$s['name'];
        $file  = $rels[$rId] ?? '';
        if ($name && $file) $sheets[] = ['name' => $name, 'file' => $file];
    }
    return $sheets;
}

// ─── Hitung baris valid ───────────────────────────────────────────────────────
function countValid($rows, $valid) {
    $n = 0;
    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        foreach ($row as $v) if (in_array(trim($v), $valid)) { $n++; break; }
    }
    return $n;
}

// ─── Deteksi kolom dari header ────────────────────────────────────────────────
// Format Excel Bang Ardan: A=Nama Indikator, B=Leveling, C=Satuan, D=PIC
function detectCols($header) {
    $cNama = false; $cLevel = false; $cSatuan = false; $cPic = false;
    foreach ($header as $i => $h) {
        $h = strtolower(trim($h));
        if ($cNama   === false && (
            strpos($h,'indikator') !== false ||
            strpos($h,'iksp')     !== false ||
            strpos($h,'iksk')     !== false ||
            strpos($h,'sasaran')  !== false ||
            strpos($h,'program')  !== false ||
            strpos($h,'kegiatan') !== false
        )) $cNama = $i;
        if ($cLevel  === false && strpos($h,'leveling') !== false) $cLevel  = $i;
        if ($cSatuan === false && strpos($h,'satuan')   !== false) $cSatuan = $i;
        if ($cPic    === false && strpos($h,'pic')      !== false) $cPic    = $i;
    }
    // Default: A(0)=Nama, B(1)=Leveling, C(2)=Satuan, D(3)=PIC
    return [
        $cNama   !== false ? $cNama   : 0,
        $cLevel  !== false ? $cLevel  : 1,
        $cSatuan !== false ? $cSatuan : 2,
        $cPic    !== false ? $cPic    : 3,
    ];
}

// ─── Scan semua sheet, pilih yang terbaik ────────────────────────────────────
function readBestSheet($filePath, $valid) {
    if (!class_exists('ZipArchive')) return ['error' => 'ZipArchive tidak tersedia di server.'];
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return ['error' => 'File tidak bisa dibuka. Pastikan format .xlsx valid.'];
    $ss     = getSharedStrings($zip);
    $sheets = getSheetList($zip);
    if (empty($sheets)) { $zip->close(); return ['error' => 'Tidak ada sheet ditemukan di file ini.']; }
    $best = null; $bestCount = 0; $summary = [];
    foreach ($sheets as $s) {
        $rows  = readSheet($zip, $s['file'], $ss);
        $count = countValid($rows, $valid);
        $summary[] = ['name' => $s['name'], 'count' => $count];
        if ($count > $bestCount) { $bestCount = $count; $best = ['info' => $s, 'rows' => $rows]; }
    }
    $zip->close();
    if (!$best || $bestCount === 0)
        return ['error' => 'Tidak ada indikator valid (IKSP/IKSK-2/IKSK-3/IKSK-4) ditemukan.', 'summary' => $summary];
    return ['rows' => $best['rows'], 'sheetName' => $best['info']['name'], 'sheetCount' => count($sheets), 'validCount' => $bestCount, 'summary' => $summary];
}

// ─── Handle POST ──────────────────────────────────────────────────────────────
$result = null; $errors = []; $preview = []; $sheetInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($file['error'] !== 0) {
        $errors[] = 'Upload gagal (error code: ' . $file['error'] . '). Coba lagi.';
    } elseif ($ext === 'xls') {
        $errors[] = 'File <strong>.xls</strong> (format Excel lama) belum didukung. Buka di Excel → File → Save As → pilih <strong>Excel Workbook (.xlsx)</strong>, lalu upload ulang.';
    } elseif ($ext !== 'xlsx') {
        $errors[] = 'Format file harus <strong>.xlsx</strong>. Pastikan file disimpan sebagai Excel Workbook (.xlsx).';
    } else {
        $data = readBestSheet($file['tmp_name'], $validLeveling);

        if (isset($data['error'])) {
            $errors[]  = $data['error'];
            $sheetInfo = $data['summary'] ?? null;
        } else {
            $rows      = $data['rows'];
            $sheetInfo = $data['summary'];

            // Cari baris header
            $headerIdx = 0;
            foreach ($rows as $i => $row) {
                $str = strtolower(implode(' ', $row));
                if (strpos($str, 'leveling') !== false || strpos($str, 'indikator') !== false) {
                    $headerIdx = $i; break;
                }
            }

            [$cNama, $cLevel, $cSatuan, $cPic] = detectCols($rows[$headerIdx] ?? []);

            $inserted    = 0;
            $skipped     = 0;
            $skippedPic  = 0;       // ← counter khusus PIC 5+ huruf
            $skippedPicList = [];   // ← daftar PIC yang diskip (untuk laporan)
            $urutan      = 1;
            $parentStack = [1 => null, 2 => null, 3 => null, 4 => null];

            $stmtIns = $db->prepare(
                "INSERT IGNORE INTO indikator (tahun_id, parent_id, nama_indikator, leveling, satuan, pic, urutan) VALUES (?,?,?,?,?,?,?)"
            );
            $stmtGet = $db->prepare(
                "SELECT id FROM indikator WHERE tahun_id=? AND nama_indikator=? AND leveling=? LIMIT 1"
            );

            foreach ($rows as $i => $row) {
                if ($i <= $headerIdx) continue;
                while (count($row) <= max($cNama, $cLevel, $cSatuan, $cPic)) $row[] = '';

                $nama     = trim($row[$cNama]   ?? '');
                $leveling = trim($row[$cLevel]  ?? '');
                $satuan   = trim($row[$cSatuan] ?? '');
                $pic      = trim($row[$cPic]    ?? '');

                // Validasi dasar
                if (!$nama || !in_array($leveling, $validLeveling) || !$satuan || !$pic) {
                    $skipped++; continue;
                }

                // ── FILTER PIC TIDAK VALID — otomatis skip ───────────────────
                // Skip jika: lebih dari 4 huruf, mengandung angka, atau mengandung titik
                $picInvalid = (strlen($pic) > 4)
                           || preg_match('/[0-9]/', $pic)
                           || strpos($pic, '.') !== false;
                if ($picInvalid) {
                    $skippedPic++;
                    if (!in_array($pic, $skippedPicList)) $skippedPicList[] = $pic;
                    continue;
                }
                // ─────────────────────────────────────────────────────────────

                $lvl      = $levelOrder[$leveling];
                $parentId = $lvl > 1 ? $parentStack[$lvl - 1] : null;

                $stmtIns->bind_param('iissssi', $tid, $parentId, $nama, $leveling, $satuan, $pic, $urutan);
                $stmtIns->execute();

                if ($stmtIns->affected_rows > 0) {
                    $newId = $db->insert_id;
                    $inserted++;
                    $parentStack[$lvl] = $newId;
                    for ($l = $lvl + 1; $l <= 4; $l++) $parentStack[$l] = null;
                    if (count($preview) < 8) $preview[] = compact('nama','leveling','satuan','pic') + ['lvl' => $lvl];
                } else {
                    $stmtGet->bind_param('iss', $tid, $nama, $leveling);
                    $stmtGet->execute();
                    $ex = $stmtGet->get_result()->fetch_assoc();
                    if ($ex) {
                        $parentStack[$lvl] = $ex['id'];
                        for ($l = $lvl + 1; $l <= 4; $l++) $parentStack[$l] = null;
                    }
                    $skipped++;
                }
                $urutan++;
            }

            $result = [
                'inserted'       => $inserted,
                'skipped'        => $skipped,
                'skippedPic'     => $skippedPic,
                'skippedPicList' => $skippedPicList,
                'sheetName'      => $data['sheetName'],
                'sheetCount'     => $data['sheetCount'],
                'validCount'     => $data['validCount'],
                'filename'       => htmlspecialchars($file['name']),
            ];
            logActivity(
                'IMPORT_EXCEL',
                "Import indikator dari file: {$file['name']} — $inserted indikator berhasil, $skipped dilewati (tahun: {$tahun['tahun']})",
                'indikator'
            );
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.drop-zone {
    border: 2px dashed #D1D5DB;
    border-radius: 12px;
    padding: 44px 24px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #FAFAFA;
    position: relative;
}
.drop-zone:hover, .drop-zone.dragover {
    border-color: #1D6F42;
    background: #F0FDF4;
}
.drop-zone input[type=file] {
    position: absolute; inset: 0;
    opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.drop-zone .dz-icon   { font-size: 52px; color: #1D6F42; display: block; margin-bottom: 14px; }
.drop-zone .dz-title  { font-size: 15px; font-weight: 700; color: var(--migas-black); margin-bottom: 4px; }
.drop-zone .dz-sub    { font-size: 12.5px; color: var(--migas-gray); }
.drop-zone .dz-chosen { margin-top: 14px; font-size: 13.5px; font-weight: 700; color: #1D6F42; display: none; }

.col-table th { background: #1A1A1A; color: #F5A623; font-weight: 700; font-size: 12px; padding: 9px 12px; }
.col-table td { font-size: 12.5px; padding: 9px 12px; vertical-align: middle; }
.col-table td:first-child { font-family: 'DM Mono', monospace; font-weight: 700; color: #1D4ED8; }

.tree-item { font-size: 12.5px; line-height: 2; font-family: 'DM Mono', monospace; }
.lv-iksp  { color: #B45309; font-weight: 800; }
.lv-iksk2 { color: #1D4ED8; font-weight: 700; }
.lv-iksk3 { color: #059669; font-weight: 700; }
.lv-iksk4 { color: #7C3AED; font-weight: 700; }

.warn-box {
    background: #FFFBEB; border: 1.5px solid #FDE68A;
    border-radius: 10px; padding: 16px 18px; font-size: 12.5px;
}
</style>

<div class="main-wrapper">

    <div class="topbar">
        <span class="topbar-title">
            <i class="bi bi-file-earmark-excel me-2" style="color:#1D6F42;"></i>Import Indikator dari Excel
        </span>
        <a href="<?= BASE_URL ?>/admin/indikator.php" class="btn-migas-outline btn" style="font-size:12.5px;padding:6px 16px;">
            <i class="bi bi-list-task me-1"></i>Lihat Semua Indikator
        </a>
    </div>

    <div class="page-content">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <!-- ── HASIL SUKSES ── -->
                <?php if ($result): ?>
                <div class="mb-4 p-4 rounded-3" style="background:#F0FDF4;border:1.5px solid #86EFAC;">
                    <div class="d-flex gap-3 align-items-start">
                        <div style="width:46px;height:46px;border-radius:50%;background:#059669;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <i class="bi bi-check-lg" style="font-size:22px;color:#fff;"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:16px;font-weight:900;color:#065F46;margin-bottom:4px;">Import Berhasil!</div>
                            <div style="font-size:12.5px;color:#047857;">
                                File: <strong><?= $result['filename'] ?></strong>
                                &nbsp;·&nbsp; Sheet: <strong>"<?= htmlspecialchars($result['sheetName']) ?>"</strong>
                                &nbsp;·&nbsp; <?= $result['sheetCount'] ?> sheet di file
                            </div>

                            <!-- Stat pills -->
                            <div class="d-flex gap-2 flex-wrap mt-3">
                                <span style="background:#059669;color:#fff;font-size:13px;font-weight:700;padding:7px 18px;border-radius:8px;">
                                    <i class="bi bi-check-circle me-1"></i><?= number_format($result['inserted']) ?> indikator diimpor
                                </span>
                                <?php if ($result['skippedPic'] > 0): ?>
                                <span style="background:#FEF3C7;color:#92400E;font-size:13px;font-weight:700;padding:7px 18px;border-radius:8px;">
                                    <i class="bi bi-funnel me-1"></i><?= number_format($result['skippedPic']) ?> difilter (PIC &gt;4 huruf)
                                </span>
                                <?php endif; ?>
                                <span style="background:#F3F4F6;color:#6B7280;font-size:13px;font-weight:700;padding:7px 18px;border-radius:8px;">
                                    <?= number_format($result['skipped']) ?> baris dilewati
                                </span>
                            </div>

                            <!-- Daftar PIC yang difilter -->
                            <?php if (!empty($result['skippedPicList'])): ?>
                            <div class="mt-3 p-3 rounded-2" style="background:#FFFBEB;border:1px solid #FDE68A;">
                                <div style="font-size:11.5px;font-weight:700;color:#92400E;margin-bottom:6px;">
                                    <i class="bi bi-funnel me-1"></i>Kode PIC yang otomatis difilter (lebih dari 4 huruf):
                                </div>
                                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                                    <?php foreach ($result['skippedPicList'] as $p): ?>
                                    <span style="font-family:'DM Mono',monospace;font-size:12px;font-weight:700;
                                                 background:#FEF3C7;color:#92400E;padding:3px 10px;border-radius:5px;
                                                 border:1px solid #FDE68A;">
                                        <?= htmlspecialchars($p) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Preview hierarki -->
                            <?php if ($preview): ?>
                            <div class="mt-3 p-3 rounded-2" style="background:#DCFCE7;border:1px solid #86EFAC;">
                                <div style="font-size:11.5px;font-weight:700;color:#065F46;margin-bottom:8px;">
                                    <i class="bi bi-diagram-3 me-1"></i>Contoh hierarki yang berhasil diimpor:
                                </div>
                                <?php foreach ($preview as $p):
                                    $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $p['lvl'] - 1);
                                    $arrow  = $p['lvl'] > 1 ? '└─&nbsp;' : '';
                                    $cls    = ['','lv-iksp','lv-iksk2','lv-iksk3','lv-iksk4'][$p['lvl']];
                                ?>
                                <div class="tree-item">
                                    <?= $indent.$arrow ?><span class="<?= $cls ?>">[<?= $p['leveling'] ?>]</span>
                                    <?= htmlspecialchars(mb_strimwidth($p['nama'], 0, 70, '…')) ?>
                                    <span style="color:#9CA3AF;">(<?= htmlspecialchars($p['pic']) ?>)</span>
                                </div>
                                <?php endforeach; ?>
                                <?php if ($result['inserted'] > 8): ?>
                                <div style="font-size:11.5px;color:#6B7280;margin-top:4px;font-style:italic;">
                                    ...dan <?= $result['inserted'] - 8 ?> indikator lainnya
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2 mt-3">
                                <a href="<?= BASE_URL ?>/admin/indikator.php" class="btn-migas btn" style="font-size:13px;padding:9px 22px;">
                                    <i class="bi bi-list-task me-1"></i>Lihat Semua Indikator
                                </a>
                                <a href="<?= BASE_URL ?>/admin/dashboard.php" class="btn-migas-outline btn" style="font-size:13px;padding:9px 22px;">
                                    <i class="bi bi-speedometer2 me-1"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── ERROR ── -->
                <?php if ($errors): ?>
                <div class="mb-4 p-4 rounded-3" style="background:#FEF2F2;border:1.5px solid #FCA5A5;">
                    <div class="d-flex gap-3 align-items-start">
                        <i class="bi bi-exclamation-triangle-fill" style="font-size:24px;color:#EF4444;flex-shrink:0;margin-top:2px;"></i>
                        <div>
                            <div style="font-size:14px;font-weight:800;color:#991B1B;margin-bottom:4px;">Import Gagal</div>
                            <div style="font-size:13px;color:#B91C1C;"><?= implode('<br>', $errors) ?></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── INFO SHEET (jika error) ── -->
                <?php if ($sheetInfo && !$result): ?>
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-layers me-2"></i>Sheet yang Ditemukan di File</h6>
                    </div>
                    <div class="p-3">
                        <?php foreach ($sheetInfo as $s): ?>
                        <div style="display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #F9FAFB;font-size:13px;">
                            <span style="font-size:16px;"><?= $s['count']>0?'✅':'⬜' ?></span>
                            <span style="font-weight:<?= $s['count']>0?'700':'400' ?>;color:<?= $s['count']>0?'var(--migas-black)':'#9CA3AF' ?>;"><?= htmlspecialchars($s['name']) ?></span>
                            <span style="color:<?= $s['count']>0?'#059669':'#9CA3AF' ?>;font-weight:700;">
                                <?= $s['count'] > 0 ? $s['count'].' indikator valid' : 'Tidak ada indikator valid' ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- ── FORM UPLOAD ── -->
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-cloud-upload me-2" style="color:#1D6F42;"></i>Upload File Excel (.xlsx)</h6>
                        <span style="font-size:11.5px;background:#D1FAE5;color:#065F46;font-weight:700;padding:3px 10px;border-radius:6px;">
                            <i class="bi bi-file-earmark-excel me-1"></i>Format .xlsx
                        </span>
                    </div>
                    <div class="p-4">
                        <form method="POST" enctype="multipart/form-data" id="importForm">

                            <!-- Drop zone -->
                            <div class="drop-zone mb-4" id="dropZone">
                                <input type="file" name="excel_file" accept=".xlsx,.xls" required id="fileInput">
                                <i class="bi bi-file-earmark-excel dz-icon"></i>
                                <div class="dz-title">Klik pilih file atau drag & drop ke sini</div>
                                <div class="dz-sub">
                                    Format <strong>.xlsx</strong> (Excel 2007+)
                                    &nbsp;·&nbsp; File boleh punya banyak sheet, otomatis dipilih yang terbaik
                                </div>
                                <div class="dz-chosen" id="chosenName">
                                    <i class="bi bi-check-circle-fill me-1" style="color:#059669;"></i>
                                    <span id="chosenText"></span>
                                </div>
                            </div>

                            <!-- Warning -->
                            <div class="warn-box mb-4">
                                <div style="font-weight:700;color:#92400E;margin-bottom:8px;">
                                    <i class="bi bi-exclamation-triangle me-1"></i>Baca sebelum import:
                                </div>
                                <ul style="margin:0;padding-left:18px;color:#78350F;line-height:2;">
                                    <li>Pastikan tabel indikator sudah <strong>dikosongkan</strong> dulu di phpMyAdmin agar tidak duplikat.</li>
                                    <li>Sistem otomatis <strong>memilih sheet</strong> yang paling banyak isi indikatornya.</li>
                                    <li>Hierarki <strong>IKSP → IKSK-2 → IKSK-3 → IKSK-4</strong> dibangun otomatis dari urutan baris.</li>
                                    <li>Indikator dengan PIC <strong>lebih dari 4 huruf, mengandung angka, atau mengandung titik</strong> (contoh: DMIRPP, DPMA.1, DPMA.2) otomatis dilewati — tidak perlu hapus manual.</li>
                                    <li>File Excel dari Bang Ardan bisa langsung diupload <strong>tanpa perlu diubah</strong>.</li>
                                </ul>
                            </div>

                            <button type="submit" class="btn-migas btn" style="padding:11px 32px;font-size:14px;" id="submitBtn">
                                <i class="bi bi-cloud-upload me-2"></i>Import Sekarang
                            </button>
                        </form>
                    </div>
                </div>

                <!-- ── PANDUAN KOLOM ── -->
                <div class="card-box">
                    <div class="card-box-header">
                        <h6><i class="bi bi-table me-2"></i>Kolom yang Dibaca dari Excel</h6>
                    </div>
                    <div class="p-4">
                        <p style="font-size:13px;color:#6B7280;margin-bottom:16px;">
                            Header kolom dideteksi otomatis. Jika tidak ditemukan, sistem pakai posisi default kolom <strong>A, B, C, D</strong>.
                        </p>
                        <table class="col-table table table-bordered mb-4">
                            <thead>
                                <tr>
                                    <th>Kolom</th>
                                    <th>Nama Header</th>
                                    <th>Isi</th>
                                    <th>Contoh</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>A (index 0)</td>
                                    <td>Indikator Kinerja Sasaran Program (IKSP) / Indikator Kinerja Sasaran Kegiatan (IKSK)</td>
                                    <td>Nama lengkap indikator</td>
                                    <td style="font-size:12px;">Indeks Ketahanan Energi Bidang Minyak dan Gas Bumi</td>
                                </tr>
                                <tr>
                                    <td>B (index 1)</td>
                                    <td>Leveling</td>
                                    <td>Jenis level</td>
                                    <td><code>IKSP</code> / <code>IKSK-2</code> / <code>IKSK-3</code> / <code>IKSK-4</code></td>
                                </tr>
                                <tr>
                                    <td>C (index 2)</td>
                                    <td>Satuan</td>
                                    <td>Satuan pengukuran</td>
                                    <td style="font-size:12px;">Laporan, Rekomendasi, Indeks, %</td>
                                </tr>
                                <tr>
                                    <td>D (index 3)</td>
                                    <td>PIC</td>
                                    <td>Kode kelompok PIC (maks. 4 huruf)</td>
                                    <td style="font-size:12px;">DMEE, DMEN, DMO, SDM</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="p-3 rounded-3" style="background:#F8FAFC;border:1.5px solid #E2E8F0;">
                            <div style="font-weight:700;font-size:13px;margin-bottom:10px;color:#1E293B;">
                                <i class="bi bi-diagram-3 me-1"></i>Contoh hierarki yang dibaca otomatis:
                            </div>
                            <div class="tree-item"><span class="lv-iksp">[IKSP]</span> Indeks Ketahanan Energi Bidang Minyak dan Gas Bumi</div>
                            <div class="tree-item">&nbsp;&nbsp;&nbsp;&nbsp;└─ <span class="lv-iksk2">[IKSK-2]</span> Ketahanan Pasokan Gas Bumi</div>
                            <div class="tree-item">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└─ <span class="lv-iksk3">[IKSK-3]</span> Fasilitasi Infrastruktur Gas</div>
                            <div class="tree-item">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;└─ <span class="lv-iksk4">[IKSK-4]</span> Laporan Evaluasi Infrastruktur Gas</div>
                            <div class="mt-3 p-2 rounded-2" style="background:#FEF3C7;border:1px solid #FDE68A;font-size:12px;color:#92400E;">
                                <i class="bi bi-funnel me-1"></i>
                                <strong>Filter otomatis aktif:</strong> PIC yang dilewati otomatis: (1) lebih dari 4 huruf, (2) mengandung angka, (3) mengandung titik. Contoh yang dilewati: <code>DMIRPP</code>, <code>DPMA.1</code>, <code>DPMR.5</code>.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
const input    = document.getElementById('fileInput');
const dz       = document.getElementById('dropZone');
const chosen   = document.getElementById('chosenName');
const chosenTx = document.getElementById('chosenText');
const btn      = document.getElementById('submitBtn');

function setFile(name) {
    chosenTx.textContent = name;
    chosen.style.display = 'block';
    btn.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Import "' + name + '"';
}

input.addEventListener('change', function() {
    if (this.files[0]) setFile(this.files[0].name);
});
dz.addEventListener('dragover',  e  => { e.preventDefault(); dz.classList.add('dragover'); });
dz.addEventListener('dragleave', () => dz.classList.remove('dragover'));
dz.addEventListener('drop', e => {
    e.preventDefault(); dz.classList.remove('dragover');
    if (e.dataTransfer.files[0]) {
        input.files = e.dataTransfer.files;
        setFile(e.dataTransfer.files[0].name);
    }
});

document.getElementById('importForm').addEventListener('submit', function() {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sedang memproses, harap tunggu…';
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>