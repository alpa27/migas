<?php
/**
 * DEBUG IMPORT — Taruh di admin/debug_import.php
 * Halaman ini TIDAK menyimpan ke database, hanya menampilkan apa yang terbaca dari file Excel.
 * Hapus file ini setelah masalah selesai!
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();
$pageTitle = 'Debug Import Excel';

// ── Helper functions (sama persis dengan import.php) ────────────────────────
function colToIndex($ref) {
    $col = strtoupper(preg_replace('/[^A-Za-z]/', '', $ref));
    $idx = 0;
    foreach (str_split($col) as $ch) $idx = $idx * 26 + ord($ch) - 64;
    return $idx - 1;
}

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

$validLeveling = ['IKSP', 'IKSK-2', 'IKSK-3', 'IKSK-4'];

// ── Cek env ─────────────────────────────────────────────────────────────────
$envOk = [
    'ZipArchive'   => class_exists('ZipArchive'),
    'SimpleXML'    => extension_loaded('simplexml'),
    'upload_tmp'   => is_writable(ini_get('upload_tmp_dir') ?: sys_get_temp_dir()),
    'upload_max'   => ini_get('upload_max_filesize'),
    'post_max'     => ini_get('post_max_size'),
];

// ── Proses upload ─────────────────────────────────────────────────────────────
$debug = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file  = $_FILES['excel_file'];
    $debug = ['file' => $file, 'steps' => []];

    $step = function($label, $ok, $detail='') use (&$debug) {
        $debug['steps'][] = ['label'=>$label, 'ok'=>$ok, 'detail'=>$detail];
    };

    $step('File diterima', $file['error']===0, 'Error code: '.$file['error'].' | Size: '.number_format($file['size']).' bytes | Name: '.$file['name']);

    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $step('Ekstensi file', $ext==='xlsx', "Ekstensi terdeteksi: .$ext (harus .xlsx)");

        if ($ext === 'xlsx') {
            $zip = new ZipArchive();
            $opened = $zip->open($file['tmp_name']);
            $step('Buka ZipArchive', $opened===true, 'Return code: '.($opened===true?'OK':$opened));

            if ($opened === true) {
                $ss     = getSharedStrings($zip);
                $sheets = getSheetList($zip);
                $step('Baca shared strings', true, count($ss).' strings ditemukan');
                $step('Baca daftar sheet', !empty($sheets), count($sheets).' sheet: '.implode(', ', array_column($sheets,'name')));

                $debug['sheets'] = [];
                $bestCount = 0;
                $bestSheet = null;

                foreach ($sheets as $s) {
                    $rows  = readSheet($zip, $s['file'], $ss);
                    $valid = 0;
                    $levelFound = [];
                    foreach ($rows as $i => $row) {
                        if ($i === 0) continue;
                        foreach ($row as $v) {
                            if (in_array(trim($v), $validLeveling)) {
                                $valid++;
                                $levelFound[trim($v)] = ($levelFound[trim($v)] ?? 0) + 1;
                                break;
                            }
                        }
                    }

                    // Cari header row
                    $headerIdx = 0;
                    foreach ($rows as $i => $row) {
                        $str = strtolower(implode(' ', $row));
                        if (strpos($str,'leveling')!==false || strpos($str,'indikator')!==false) { $headerIdx=$i; break; }
                    }

                    // Deteksi kolom
                    $header  = $rows[$headerIdx] ?? [];
                    $cols    = [1,2,3,4]; // default
                    $cNama=false; $cLevel=false; $cSatuan=false; $cPic=false;
                    foreach ($header as $i => $h) {
                        $h2 = strtolower(trim($h));
                        if ($cNama===false && (strpos($h2,'iksp')!==false||strpos($h2,'iksk')!==false||(strpos($h2,'indikator')!==false&&strpos($h2,'sasaran')===false))) $cNama=$i;
                        if ($cLevel===false && strpos($h2,'leveling')!==false) $cLevel=$i;
                        if ($cSatuan===false && strpos($h2,'satuan')!==false)  $cSatuan=$i;
                        if ($cPic===false && strpos($h2,'pic')!==false)        $cPic=$i;
                    }
                    $cNama   = $cNama   !== false ? $cNama   : 0;
                    $cLevel  = $cLevel  !== false ? $cLevel  : 1;
                    $cSatuan = $cSatuan !== false ? $cSatuan : 2;
                    $cPic    = $cPic    !== false ? $cPic    : 3;

                    // Preview 5 baris data
                    $preview = [];
                    foreach ($rows as $i => $row) {
                        if ($i <= $headerIdx) continue;
                        if (count($preview) >= 5) break;
                        while (count($row) <= max($cNama,$cLevel,$cSatuan,$cPic)) $row[] = '';
                        $preview[] = [
                            'row'     => $i+1,
                            'B_nama'  => $row[$cNama]   ?? '(kosong)',
                            'C_level' => $row[$cLevel]  ?? '(kosong)',
                            'D_sat'   => $row[$cSatuan] ?? '(kosong)',
                            'E_pic'   => $row[$cPic]    ?? '(kosong)',
                            'valid'   => in_array(trim($row[$cLevel] ?? ''), $validLeveling),
                        ];
                    }

                    $debug['sheets'][] = [
                        'name'       => $s['name'],
                        'totalRows'  => count($rows),
                        'headerRow'  => $headerIdx + 1,
                        'headerData' => array_slice($header, 0, 8),
                        'colDetect'  => ['nama'=>$cNama,'level'=>$cLevel,'satuan'=>$cSatuan,'pic'=>$cPic],
                        'validCount' => $valid,
                        'levelBreak' => $levelFound,
                        'preview'    => $preview,
                    ];

                    if ($valid > $bestCount) { $bestCount = $valid; $bestSheet = $s['name']; }
                }

                $zip->close();
                $step('Sheet terbaik dipilih', $bestCount > 0, $bestSheet ? "\"$bestSheet\" ($bestCount baris valid)" : 'Tidak ada sheet dengan leveling valid!');
                $debug['bestSheet'] = $bestSheet;
                $debug['bestCount'] = $bestCount;
            }
        }
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
.dbg-step { display:flex;align-items:flex-start;gap:12px;padding:10px 0;border-bottom:1px solid #F3F4F6; }
.dbg-step:last-child { border-bottom:none; }
.dbg-ok  { width:24px;height:24px;border-radius:50%;background:#D1FAE5;color:#059669;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.dbg-err { width:24px;height:24px;border-radius:50%;background:#FEE2E2;color:#EF4444;display:flex;align-items:center;justify-content:center;font-size:13px;flex-shrink:0; }
.dbg-lbl { font-size:13px;font-weight:700;color:#111;margin-bottom:2px; }
.dbg-det { font-size:12px;color:#6B7280;font-family:'DM Mono',monospace; }
.sheet-card { border:1.5px solid #E5E7EB;border-radius:10px;margin-bottom:16px;overflow:hidden; }
.sheet-head { padding:12px 16px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between; }
.env-row { display:flex;align-items:center;gap:10px;padding:7px 0;border-bottom:1px solid #F9FAFB;font-size:13px; }
</style>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title" style="color:#EF4444;">
            <i class="bi bi-bug me-2"></i>Debug Import Excel
        </span>
        <a href="<?= BASE_URL ?>/admin/import.php" class="btn-migas-outline btn" style="font-size:12.5px;padding:6px 16px;">
            ← Kembali ke Import
        </a>
    </div>

    <div class="page-content">
        <div class="row justify-content-center">
            <div class="col-lg-10">

                <!-- Banner peringatan -->
                <div class="mb-4 p-3 rounded-3" style="background:#FEF9C3;border:1.5px solid #FDE047;">
                    <i class="bi bi-exclamation-triangle-fill me-2" style="color:#D97706;"></i>
                    <strong>Halaman Debug</strong> — File TIDAK disimpan ke database. Hanya untuk diagnosa masalah. Hapus file ini setelah selesai.
                </div>

                <!-- ENV CHECK -->
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-server me-2"></i>Cek Environment Server</h6>
                    </div>
                    <div class="p-3">
                        <?php foreach ($envOk as $label => $val): ?>
                        <div class="env-row">
                            <span class="<?= $val&&$val!==false?'dbg-ok':'dbg-err' ?>">
                                <i class="bi bi-<?= $val&&$val!==false?'check':'x' ?>"></i>
                            </span>
                            <span style="font-weight:700;width:160px;"><?= $label ?></span>
                            <span style="font-family:monospace;font-size:12px;color:#374151;"><?= is_bool($val) ? ($val?'✅ Tersedia':'❌ Tidak tersedia') : htmlspecialchars($val) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- UPLOAD FORM -->
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-upload me-2"></i>Upload File Excel untuk Diagnosa</h6>
                    </div>
                    <div class="p-4">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="d-flex gap-3 align-items-end">
                                <div style="flex:1;">
                                    <label class="form-label">Pilih file Excel (.xlsx) yang gagal diimport</label>
                                    <input type="file" name="excel_file" accept=".xlsx,.xls" class="form-control" required>
                                </div>
                                <button type="submit" class="btn" style="background:#EF4444;color:#fff;font-weight:700;padding:10px 24px;border-radius:8px;border:none;white-space:nowrap;">
                                    <i class="bi bi-bug me-1"></i>Diagnosa
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($debug): ?>

                <!-- HASIL DIAGNOSA -->
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-list-check me-2"></i>Langkah-langkah yang Dijalankan</h6>
                    </div>
                    <div class="p-3">
                        <?php foreach ($debug['steps'] as $s): ?>
                        <div class="dbg-step">
                            <div class="<?= $s['ok']?'dbg-ok':'dbg-err' ?>">
                                <i class="bi bi-<?= $s['ok']?'check':'x' ?>"></i>
                            </div>
                            <div>
                                <div class="dbg-lbl"><?= $s['label'] ?></div>
                                <div class="dbg-det"><?= htmlspecialchars($s['detail']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- DETAIL PER SHEET -->
                <?php if (!empty($debug['sheets'])): ?>
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-layers me-2"></i>Detail Setiap Sheet</h6>
                        <span style="font-size:12px;color:#9CA3AF;">Sheet terpilih: <strong style="color:#059669;"><?= htmlspecialchars($debug['bestSheet'] ?? '-') ?></strong> (<?= $debug['bestCount'] ?> valid)</span>
                    </div>
                    <div class="p-3">
                    <?php foreach ($debug['sheets'] as $sh): ?>
                        <div class="sheet-card">
                            <div class="sheet-head">
                                <div>
                                    <span style="font-weight:800;font-size:14px;"><?= htmlspecialchars($sh['name']) ?></span>
                                    <?php if ($sh['name'] === ($debug['bestSheet']??'')): ?>
                                        <span style="margin-left:8px;font-size:11px;background:#D1FAE5;color:#059669;padding:2px 8px;border-radius:4px;font-weight:700;">⭐ DIPILIH</span>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size:12px;color:#6B7280;"><?= $sh['totalRows'] ?> baris total &nbsp;·&nbsp; <span style="color:<?= $sh['validCount']>0?'#059669':'#EF4444' ?>;font-weight:700;"><?= $sh['validCount'] ?> valid</span></span>
                            </div>
                            <div class="p-3">

                                <!-- Header row info -->
                                <div class="mb-3">
                                    <div style="font-size:11.5px;font-weight:700;color:#6B7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                        Header terdeteksi (baris <?= $sh['headerRow'] ?>)
                                    </div>
                                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                        <?php foreach ($sh['headerData'] as $ci => $hv): ?>
                                        <span style="font-size:11px;padding:3px 8px;border-radius:5px;font-family:monospace;
                                            background:<?= in_array($ci, array_values($sh['colDetect']))?'#FFF7E6':'#F3F4F6' ?>;
                                            border:1px solid <?= in_array($ci, array_values($sh['colDetect']))?'#F5A623':'#E5E7EB' ?>;
                                            color:<?= in_array($ci, array_values($sh['colDetect']))?'#92400E':'#6B7280' ?>;">
                                            [<?= chr(65+$ci) ?>] <?= htmlspecialchars($hv ?: '(kosong)') ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Kolom terdeteksi -->
                                <div class="mb-3">
                                    <div style="font-size:11.5px;font-weight:700;color:#6B7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                        Kolom yang akan dibaca
                                    </div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <?php
                                        $colNames = ['nama'=>'Nama Indikator','level'=>'Leveling','satuan'=>'Satuan','pic'=>'PIC'];
                                        foreach ($sh['colDetect'] as $k => $ci):
                                        ?>
                                        <div style="padding:6px 12px;border-radius:7px;background:#F0FDF4;border:1px solid #86EFAC;font-size:12px;">
                                            <span style="font-weight:700;color:#059669;"><?= $colNames[$k] ?></span>
                                            <span style="color:#374151;margin-left:4px;">→ Kolom <?= chr(65+$ci) ?> (index <?= $ci ?>)</span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>

                                <!-- Level breakdown -->
                                <?php if ($sh['levelBreak']): ?>
                                <div class="mb-3">
                                    <div style="font-size:11.5px;font-weight:700;color:#6B7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                        Leveling ditemukan
                                    </div>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <?php foreach ($sh['levelBreak'] as $lv => $cnt): ?>
                                        <span style="font-size:12px;padding:4px 12px;border-radius:6px;font-weight:700;
                                            background:<?= ['IKSP'=>'#FEF3C7','IKSK-2'=>'#DBEAFE','IKSK-3'=>'#D1FAE5','IKSK-4'=>'#EDE9FE'][$lv]??'#F3F4F6' ?>;
                                            color:<?= ['IKSP'=>'#92400E','IKSK-2'=>'#1D4ED8','IKSK-3'=>'#065F46','IKSK-4'=>'#5B21B6'][$lv]??'#374151' ?>;">
                                            <?= $lv ?>: <?= $cnt ?>
                                        </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Preview rows -->
                                <?php if ($sh['preview']): ?>
                                <div>
                                    <div style="font-size:11.5px;font-weight:700;color:#6B7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;">
                                        Preview 5 baris data (setelah header)
                                    </div>
                                    <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;font-size:12px;">
                                        <thead>
                                            <tr style="background:#1A1A1A;color:#F5A623;">
                                                <th style="padding:7px 10px;text-align:left;">Baris</th>
                                                <th style="padding:7px 10px;text-align:left;">Nama Indikator (B)</th>
                                                <th style="padding:7px 10px;text-align:left;">Leveling (C)</th>
                                                <th style="padding:7px 10px;text-align:left;">Satuan (D)</th>
                                                <th style="padding:7px 10px;text-align:left;">PIC (E)</th>
                                                <th style="padding:7px 10px;text-align:center;">Valid?</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sh['preview'] as $pr): ?>
                                            <tr style="background:<?= $pr['valid']?'#F0FDF4':'#FEF2F2' ?>;border-bottom:1px solid #E5E7EB;">
                                                <td style="padding:7px 10px;font-weight:700;color:#6B7280;"><?= $pr['row'] ?></td>
                                                <td style="padding:7px 10px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($pr['B_nama']) ?></td>
                                                <td style="padding:7px 10px;font-weight:700;color:<?= $pr['valid']?'#059669':'#EF4444' ?>;"><?= htmlspecialchars($pr['C_level']) ?></td>
                                                <td style="padding:7px 10px;"><?= htmlspecialchars($pr['D_sat']) ?></td>
                                                <td style="padding:7px 10px;"><?= htmlspecialchars($pr['E_pic']) ?></td>
                                                <td style="padding:7px 10px;text-align:center;"><?= $pr['valid']?'✅':'❌' ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div style="font-size:12.5px;color:#9CA3AF;font-style:italic;">Tidak ada baris data setelah header.</div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- KESIMPULAN -->
                <div class="card-box mb-4">
                    <div class="card-box-header">
                        <h6><i class="bi bi-lightbulb me-2"></i>Kesimpulan & Solusi</h6>
                    </div>
                    <div class="p-4" style="font-size:13px;line-height:1.9;">
                        <?php
                        $allOk = array_filter($debug['steps'], fn($s)=>!$s['ok']);
                        if (!empty($allOk)):
                            foreach ($debug['steps'] as $s):
                                if (!$s['ok']):
                        ?>
                        <div style="background:#FEF2F2;border-radius:8px;padding:14px;margin-bottom:10px;border-left:4px solid #EF4444;">
                            <strong style="color:#991B1B;">❌ Masalah: <?= $s['label'] ?></strong><br>
                            <span style="color:#B91C1C;"><?= htmlspecialchars($s['detail']) ?></span><br><br>
                            <?php if (strpos($s['label'],'ZipArchive')!==false): ?>
                                <strong>Solusi:</strong> Aktifkan ekstensi <code>php_zip</code> di XAMPP → <code>php.ini</code> → cari <code>;extension=zip</code> → hapus titik koma → restart Apache.
                            <?php elseif (strpos($s['label'],'Ekstensi')!==false): ?>
                                <strong>Solusi:</strong> Simpan ulang file Excel → File → Save As → pilih <strong>Excel Workbook (.xlsx)</strong>.
                            <?php elseif (strpos($s['label'],'Sheet terbaik')!==false): ?>
                                <strong>Solusi:</strong> Leveling di kolom C tidak terbaca. Kemungkinan: (1) Kolom leveling bukan di C, (2) Nilai leveling tidak persis <code>IKSP</code>/<code>IKSK-2</code>/<code>IKSK-3</code>/<code>IKSK-4</code>, (3) Ada spasi/karakter tersembunyi.
                            <?php else: ?>
                                <strong>Solusi:</strong> Coba upload ulang. Jika masih gagal, hubungi admin server.
                            <?php endif; ?>
                        </div>
                        <?php
                                endif;
                            endforeach;
                        else:
                        ?>
                        <div style="background:#F0FDF4;border-radius:8px;padding:14px;border-left:4px solid #059669;">
                            <strong style="color:#065F46;">✅ Semua langkah berhasil!</strong><br>
                            File Excel terbaca dengan baik.
                            <?php if (($debug['bestCount']??0) > 0): ?>
                                Sheet <strong>"<?= htmlspecialchars($debug['bestSheet']) ?>"</strong> berisi <strong><?= $debug['bestCount'] ?></strong> indikator valid dan siap diimport.<br><br>
                                Kalau import di halaman utama masih gagal, kemungkinan masalahnya di <strong>database</strong>:
                                <ul style="margin-top:8px;">
                                    <li>Cek apakah kolom <code>parent_id</code> sudah ada di tabel <code>indikator</code></li>
                                    <li>Cek apakah ada constraint/unique yang mencegah insert</li>
                                    <li>Coba kosongkan tabel dulu: <code>DELETE FROM realisasi; DELETE FROM indikator;</code></li>
                                </ul>
                            <?php else: ?>
                                Tapi <strong>tidak ada baris valid</strong> yang ditemukan di semua sheet. Periksa nilai di kolom Leveling apakah persis <code>IKSP</code>, <code>IKSK-2</code>, <code>IKSK-3</code>, atau <code>IKSK-4</code>.
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php endif; // end $debug ?>

            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>