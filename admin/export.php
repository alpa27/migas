<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$picFilter = $_GET['pic'] ?? '';

$where = ["i.tahun_id = $tid"];
$params = []; $types = '';
if ($picFilter) {
    $where[] = "i.pic = ?";
    $params[] = $picFilter;
    $types .= 's';
}
$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT i.nama_indikator, i.leveling, i.satuan, i.pic,
           COALESCE(r.tw1,'') tw1, COALESCE(r.tw2,'') tw2,
           COALESCE(r.tw3,'') tw3, COALESCE(r.tw4,'') tw4,
           COALESCE(r.link_tw1,'') link_tw1, COALESCE(r.evaluasi_tw1,'') eval_tw1, COALESCE(r.tindak_lanjut_tw1,'') rtl_tw1,
           COALESCE(r.link_tw2,'') link_tw2, COALESCE(r.evaluasi_tw2,'') eval_tw2, COALESCE(r.tindak_lanjut_tw2,'') rtl_tw2,
           COALESCE(r.link_tw3,'') link_tw3, COALESCE(r.evaluasi_tw3,'') eval_tw3, COALESCE(r.tindak_lanjut_tw3,'') rtl_tw3,
           COALESCE(r.link_tw4,'') link_tw4, COALESCE(r.evaluasi_tw4,'') eval_tw4, COALESCE(r.tindak_lanjut_tw4,'') rtl_tw4
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
    WHERE $whereSQL
    ORDER BY i.pic, i.urutan
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$filename = 'laporan_kinerja_migas_' . ($tahun['tahun'] ?? date('Y')) . ($picFilter ? "_$picFilter" : '_semua') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
echo "\xEF\xBB\xBF";
?>
<html><head><meta charset="UTF-8">
<style>
    body { font-family: Arial, sans-serif; font-size: 9pt; }
    table { border-collapse: collapse; width: 100%; }
    .th-main { background:#1A1A1A; color:#F5A623; border:1px solid #555; padding:6px 8px; text-align:center; font-weight:bold; font-size:9pt; }
    .th-tw1  { background:#1D4ED8; color:#fff; border:1px solid #555; padding:5px 7px; text-align:center; font-weight:bold; font-size:8.5pt; }
    .th-tw2  { background:#065F46; color:#fff; border:1px solid #555; padding:5px 7px; text-align:center; font-weight:bold; font-size:8.5pt; }
    .th-tw3  { background:#92400E; color:#fff; border:1px solid #555; padding:5px 7px; text-align:center; font-weight:bold; font-size:8.5pt; }
    .th-tw4  { background:#5B21B6; color:#fff; border:1px solid #555; padding:5px 7px; text-align:center; font-weight:bold; font-size:8.5pt; }
    td { border:1px solid #ccc; padding:5px 7px; vertical-align:top; font-size:8.5pt; }
    tr:nth-child(even) td { background:#FAFAFA; }
    .num { text-align:center; }
    .center { text-align:center; }
    h2 { color:#1A1A1A; margin-bottom:4px; }
</style>
</head><body>
<h2>Laporan Indikator Kinerja — Ditjen Migas</h2>
<p style="font-size:9pt;color:#555;margin-bottom:12px;">
    Tahun: <?= $tahun['tahun'] ?? '-' ?><?= $picFilter ? " | Kelompok: $picFilter" : '' ?> | Dicetak: <?= date('d/m/Y H:i') ?>
</p>
<table>
<thead>
<tr>
    <th class="th-main" rowspan="2">No</th>
    <th class="th-main" rowspan="2">Indikator Kinerja</th>
    <th class="th-main" rowspan="2">Leveling</th>
    <th class="th-main" rowspan="2">Satuan</th>
    <th class="th-main" rowspan="2">PIC</th>
    <th class="th-tw1" colspan="4">Triwulan I</th>
    <th class="th-tw2" colspan="4">Triwulan II</th>
    <th class="th-tw3" colspan="4">Triwulan III</th>
    <th class="th-tw4" colspan="4">Triwulan IV</th>
</tr>
<tr>
    <th class="th-tw1">Realisasi</th><th class="th-tw1">Link Data Dukung</th><th class="th-tw1">Evaluasi</th><th class="th-tw1">Tindak Lanjut</th>
    <th class="th-tw2">Realisasi</th><th class="th-tw2">Link Data Dukung</th><th class="th-tw2">Evaluasi</th><th class="th-tw2">Tindak Lanjut</th>
    <th class="th-tw3">Realisasi</th><th class="th-tw3">Link Data Dukung</th><th class="th-tw3">Evaluasi</th><th class="th-tw3">Tindak Lanjut</th>
    <th class="th-tw4">Realisasi</th><th class="th-tw4">Link Data Dukung</th><th class="th-tw4">Evaluasi</th><th class="th-tw4">Tindak Lanjut</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $i => $row): ?>
<tr>
    <td class="center"><?= $i+1 ?></td>
    <td><?= htmlspecialchars($row['nama_indikator']) ?></td>
    <td class="center"><?= htmlspecialchars($row['leveling']) ?></td>
    <td class="center"><?= htmlspecialchars($row['satuan']) ?></td>
    <td class="center"><b><?= htmlspecialchars($row['pic']) ?></b></td>
    <td class="num"><?= $row['tw1'] ?></td>
    <td><?= htmlspecialchars($row['link_tw1']) ?></td>
    <td><?= htmlspecialchars($row['eval_tw1']) ?></td>
    <td><?= htmlspecialchars($row['rtl_tw1']) ?></td>
    <td class="num"><?= $row['tw2'] ?></td>
    <td><?= htmlspecialchars($row['link_tw2']) ?></td>
    <td><?= htmlspecialchars($row['eval_tw2']) ?></td>
    <td><?= htmlspecialchars($row['rtl_tw2']) ?></td>
    <td class="num"><?= $row['tw3'] ?></td>
    <td><?= htmlspecialchars($row['link_tw3']) ?></td>
    <td><?= htmlspecialchars($row['eval_tw3']) ?></td>
    <td><?= htmlspecialchars($row['rtl_tw3']) ?></td>
    <td class="num"><?= $row['tw4'] ?></td>
    <td><?= htmlspecialchars($row['link_tw4']) ?></td>
    <td><?= htmlspecialchars($row['eval_tw4']) ?></td>
    <td><?= htmlspecialchars($row['rtl_tw4']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body></html>