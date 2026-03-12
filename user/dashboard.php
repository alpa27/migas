<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireLogin();
if ($_SESSION['role'] === 'admin') redirect(BASE_URL . '/admin/dashboard.php');

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$user  = getCurrentUser();
$kode  = $user['kode_kelompok'];
$pageTitle = 'Dashboard';

[$condition, $param] = buildPicCondition($kode, 'i');

$stmt = $db->prepare("SELECT COUNT(*) c FROM indikator i WHERE i.tahun_id = $tid AND $condition");
$stmt->bind_param('s', $param);
$stmt->execute();
$totalIndikator = $stmt->get_result()->fetch_assoc()['c'];

$stmt2 = $db->prepare("
    SELECT COUNT(*) c FROM realisasi r
    JOIN indikator i ON r.indikator_id = i.id
    WHERE i.tahun_id = $tid AND $condition
    AND (r.tw1 IS NOT NULL OR r.tw2 IS NOT NULL OR r.tw3 IS NOT NULL OR r.tw4 IS NOT NULL)
");
$stmt2->bind_param('s', $param);
$stmt2->execute();
$totalIsi = $stmt2->get_result()->fetch_assoc()['c'];

$pctIsi = $totalIndikator > 0 ? round($totalIsi/$totalIndikator*100) : 0;

// TW stats
$twStats = [];
foreach([1,2,3,4] as $tw) {
    $s = $db->prepare("SELECT COUNT(*) c FROM realisasi r JOIN indikator i ON r.indikator_id=i.id WHERE i.tahun_id=$tid AND $condition AND r.tw$tw IS NOT NULL");
    $s->bind_param('s', $param);
    $s->execute();
    $twStats[$tw] = $s->get_result()->fetch_assoc()['c'];
}

// Recent indikator (belum terisi)
$stmt3 = $db->prepare("
    SELECT i.*, r.tw1, r.tw2, r.tw3, r.tw4
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
    WHERE i.tahun_id = $tid AND $condition
    ORDER BY (r.id IS NULL) DESC, i.urutan
    LIMIT 10
");
$stmt3->bind_param('s', $param);
$stmt3->execute();
$recentRows = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:12.5px;color:#6B7280;">Tahun <?= $tahun['tahun'] ?? '-' ?></span>
            <span class="badge-pic"><?= htmlspecialchars($kode) ?></span>
        </div>
    </div>

    <div class="page-content">

        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Greeting -->
        <div class="mb-4 p-4 rounded-3" style="background:linear-gradient(135deg,#1A1A1A 0%,#2D2D2D 100%);border:1px solid #333;">
            <div style="font-size:13px;color:rgba(255,255,255,.55);margin-bottom:4px;">Selamat datang,</div>
            <div style="font-size:20px;font-weight:800;color:#fff;"><?= htmlspecialchars($user['nama']) ?></div>
            <div style="font-size:12.5px;color:#F5A623;font-weight:600;margin-top:2px;">Kelompok <?= htmlspecialchars($kode) ?></div>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="bi bi-list-task"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalIndikator ?></div>
                        <div class="stat-label">Total Indikator Saya</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalIsi ?></div>
                        <div class="stat-label">Sudah Terisi</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="stat-icon <?= $pctIsi >= 80 ? 'green' : ($pctIsi >= 50 ? 'yellow' : 'blue') ?>"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="stat-value"><?= $pctIsi ?>%</div>
                        <div class="stat-label">Progress Pengisian</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TW Progress -->
        <div class="card-box mb-4">
            <div class="card-box-header">
                <h6><i class="bi bi-bar-chart-steps me-2"></i>Progress per Triwulan</h6>
                <a href="<?= BASE_URL ?>/user/realisasi.php" class="btn-migas btn" style="font-size:12px;padding:5px 14px;">
                    <i class="bi bi-pencil me-1"></i>Input Realisasi
                </a>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <?php foreach([1,2,3,4] as $tw):
                        $isOpen = isTwOpen($tahun, $tw);
                        $pct = $totalIndikator > 0 ? round($twStats[$tw]/$totalIndikator*100) : 0;
                    ?>
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size:13px;font-weight:700;">TW <?= $tw ?></span>
                            <div class="d-flex align-items-center gap-2">
                                <?php if (!$isOpen): ?>
                                    <span style="font-size:10px;background:#FEF2F2;color:#DC2626;padding:2px 6px;border-radius:4px;font-weight:600;">Tutup</span>
                                <?php else: ?>
                                    <span style="font-size:10px;background:#D1FAE5;color:#059669;padding:2px 6px;border-radius:4px;font-weight:600;">Buka</span>
                                <?php endif; ?>
                                <span style="font-size:12px;color:#6B7280;"><?= $pct ?>%</span>
                            </div>
                        </div>
                        <div class="progress tw-progress">
                            <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'#059669':($pct>=50?'#F5A623':'#EF4444') ?>"></div>
                        </div>
                        <div style="font-size:11px;color:#9CA3AF;margin-top:3px;"><?= $twStats[$tw] ?>/<?= $totalIndikator ?> terisi</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Indikator Table Preview -->
        <div class="card-box">
            <div class="card-box-header">
                <h6><i class="bi bi-table me-2"></i>Indikator Saya</h6>
                <a href="<?= BASE_URL ?>/user/indikator.php" class="btn-migas-outline btn" style="font-size:12px;padding:5px 12px;">
                    Lihat Semua <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Indikator</th>
                            <th>Level</th>
                            <th class="text-center">TW I</th>
                            <th class="text-center">TW II</th>
                            <th class="text-center">TW III</th>
                            <th class="text-center">TW IV</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRows as $row):
                            $isEmpty = $row['tw1']===null && $row['tw2']===null && $row['tw3']===null && $row['tw4']===null;
                            $lvl = str_replace('-','',strtolower($row['leveling']));
                        ?>
                        <tr>
                            <td style="max-width:300px;font-size:13px;">
                                <?php if ($isEmpty): ?>
                                    <span style="width:7px;height:7px;background:#EF4444;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                                <?php else: ?>
                                    <span style="width:7px;height:7px;background:#059669;border-radius:50%;display:inline-block;margin-right:6px;"></span>
                                <?php endif; ?>
                                <?= htmlspecialchars($row['nama_indikator']) ?>
                            </td>
                            <td><span class="badge-<?= $lvl ?>"><?= $row['leveling'] ?></span></td>
                            <?php foreach([1,2,3,4] as $tw): ?>
                                <td class="text-center" style="font-family:'DM Mono',monospace;font-size:12px;">
                                    <?= $row["tw$tw"] !== null ? number_format($row["tw$tw"],2) : '<span style="color:#D1D5DB;">—</span>' ?>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $row['id'] ?>"
                                   class="btn btn-sm" style="background:#F5A623;color:#1A1A1A;font-size:11px;border-radius:6px;font-weight:700;padding:3px 10px;">
                                    <i class="bi bi-pencil"></i> Isi
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
