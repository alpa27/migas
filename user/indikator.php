<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
if ($_SESSION['role'] === 'admin') redirect(BASE_URL . '/admin/indikator.php');

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$user  = getCurrentUser();
$kode  = $user['kode_kelompok'];
$pageTitle = 'Indikator Saya';

[$condition, $param] = buildPicCondition($kode, 'i');

$levelFilter  = $_GET['leveling'] ?? '';
$searchFilter = $_GET['q'] ?? '';

$where = ["i.tahun_id = $tid", $condition];
$params = [$param]; $types = 's';

if ($levelFilter) {
    $where[] = "i.leveling = ?";
    $params[] = $levelFilter;
    $types .= 's';
}

if ($searchFilter) {
    $where[] = "i.nama_indikator LIKE ?";
    $params[] = "%$searchFilter%";
    $types .= 's';
}

$whereSQL = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT i.*, r.tw1, r.tw2, r.tw3, r.tw4, r.total_realisasi,
           r.link_tw1, r.evaluasi_tw1, r.tindak_lanjut_tw1,
           r.link_tw2, r.evaluasi_tw2, r.tindak_lanjut_tw2,
           r.link_tw3, r.evaluasi_tw3, r.tindak_lanjut_tw3,
           r.link_tw4, r.evaluasi_tw4, r.tindak_lanjut_tw4
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
    WHERE $whereSQL
    ORDER BY i.urutan
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-list-task me-2"></i>Indikator Saya</span>
        <a href="<?= BASE_URL ?>/user/realisasi.php" class="btn-migas btn" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-pencil me-1"></i>Input Realisasi
        </a>
    </div>

    <div class="page-content">
        <!-- Filter -->
        <div class="card-box mb-3 p-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Cari</label>
                    <input type="text" name="q" class="form-control" placeholder="Nama indikator..." value="<?= htmlspecialchars($searchFilter) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Leveling</label>
                    <select name="leveling" class="form-select">
                        <option value="">Semua</option>
                        <option value="IKSP" <?= $levelFilter==='IKSP'?'selected':'' ?>>IKSP</option>
                        <option value="IKSK-2" <?= $levelFilter==='IKSK-2'?'selected':'' ?>>IKSK-2</option>
                        <option value="IKSK-3" <?= $levelFilter==='IKSK-3'?'selected':'' ?>>IKSK-3</option>
                        <option value="IKSK-4" <?= $levelFilter==='IKSK-4'?'selected':'' ?>>IKSK-4</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-dark w-100" style="border-radius:8px;font-weight:600;font-size:13px;">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="<?= BASE_URL ?>/user/indikator.php" class="btn btn-outline-secondary w-100" style="border-radius:8px;font-size:13px;">Reset</a>
                </div>
            </form>
        </div>

        <div class="card-box">
            <div class="card-box-header">
                <h6><i class="bi bi-table me-2"></i>Daftar Indikator
                    <span class="ms-2" style="font-size:12px;color:#6B7280;font-weight:500;"><?= count($rows) ?> data</span>
                </h6>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Indikator Kinerja</th>
                            <th>Level</th>
                            <th>Satuan</th>
                            <th>PIC</th>
                            <th class="text-center">TW I</th>
                            <th class="text-center">TW II</th>
                            <th class="text-center">TW III</th>
                            <th class="text-center">TW IV</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $row):
                            $isEmpty = $row['tw1']===null && $row['tw2']===null && $row['tw3']===null && $row['tw4']===null;
                            $lvl = str_replace('-','',strtolower($row['leveling']));
                        ?>
                        <tr>
                            <td style="color:#9CA3AF;font-size:12px;"><?= $i+1 ?></td>
                            <td style="max-width:320px;font-size:13px;">
                                <span style="width:6px;height:6px;border-radius:50%;display:inline-block;margin-right:6px;background:<?= $isEmpty ? '#EF4444' : '#059669' ?>"></span>
                                <?= htmlspecialchars($row['nama_indikator']) ?>
                            </td>
                            <td><span class="badge-<?= $lvl ?>"><?= $row['leveling'] ?></span></td>
                            <td style="font-size:12px;color:#6B7280;"><?= htmlspecialchars($row['satuan']) ?></td>
                            <td><span class="badge-pic"><?= htmlspecialchars($row['pic']) ?></span></td>
                            <?php foreach([1,2,3,4] as $tw): ?>
                                <td class="text-center" style="font-family:'DM Mono',monospace;font-size:12px;">
                                    <?= $row["tw$tw"] !== null ? number_format($row["tw$tw"],2) : '<span style="color:#D1D5DB;">—</span>' ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center fw-bold" style="font-family:'DM Mono',monospace;font-size:12px;">
                                <?= $row['total_realisasi'] !== null ? number_format($row['total_realisasi'],2) : '<span style="color:#D1D5DB;">—</span>' ?>
                            </td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm" style="background:#F5A623;color:#1A1A1A;font-size:11px;border-radius:6px;font-weight:700;padding:3px 10px;">
                                    <i class="bi bi-pencil"></i> <?= $isEmpty ? 'Isi' : 'Edit' ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (!$rows): ?>
                        <tr>
                            <td colspan="11" class="text-center py-4" style="color:#9CA3AF;font-size:13px;">
                                <i class="bi bi-inbox fs-4 d-block mb-2"></i>Tidak ada indikator
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>