<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$pageTitle = 'Dashboard Admin';

// Stats
$totalIndikator = $db->query("SELECT COUNT(*) c FROM indikator WHERE tahun_id = $tid")->fetch_assoc()['c'];
$totalKelompok  = $db->query("SELECT COUNT(DISTINCT pic) c FROM indikator WHERE tahun_id = $tid")->fetch_assoc()['c'];
$totalIsi       = $db->query("SELECT COUNT(*) c FROM realisasi r JOIN indikator i ON r.indikator_id=i.id WHERE i.tahun_id=$tid AND (r.tw1 IS NOT NULL OR r.tw2 IS NOT NULL OR r.tw3 IS NOT NULL OR r.tw4 IS NOT NULL)")->fetch_assoc()['c'];
$pctIsi         = $totalIndikator > 0 ? round($totalIsi / $totalIndikator * 100) : 0;

// Per-kelompok summary
$kelompokData = $db->query("
    SELECT i.pic,
           COUNT(*) AS total,
           SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) AS terisi
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
    WHERE i.tahun_id = $tid
    GROUP BY i.pic
    ORDER BY i.pic
")->fetch_all(MYSQLI_ASSOC);

// TW completion per kolom
$twStats = [];
foreach([1,2,3,4] as $tw) {
    $r = $db->query("SELECT COUNT(*) c FROM realisasi r JOIN indikator i ON r.indikator_id=i.id WHERE i.tahun_id=$tid AND r.tw$tw IS NOT NULL")->fetch_assoc();
    $twStats[$tw] = $r['c'];
}

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</span>
        <div class="d-flex align-items-center gap-2">
            <span class="badge" style="background:#1A1A1A;color:#F5A623;font-size:12px;padding:6px 12px;border-radius:6px;">
                Tahun <?= $tahun['tahun'] ?? '-' ?>
            </span>
            <a href="<?= BASE_URL ?>/admin/export.php" class="btn-migas btn" style="font-size:12.5px;padding:6px 14px;">
                <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
            </a>
        </div>
    </div>

    <div class="page-content">

        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon yellow"><i class="bi bi-list-task"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalIndikator ?></div>
                        <div class="stat-label">Total Indikator</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon black"><i class="bi bi-diagram-3"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalKelompok ?></div>
                        <div class="stat-label">Kelompok Aktif</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalIsi ?></div>
                        <div class="stat-label">Indikator Terisi</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="bi bi-percent"></i></div>
                    <div>
                        <div class="stat-value"><?= $pctIsi ?>%</div>
                        <div class="stat-label">Tingkat Pengisian</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TW Progress -->
        <div class="card-box mb-4">
            <div class="card-box-header">
                <h6><i class="bi bi-bar-chart-line me-2"></i>Rekapitulasi Pengisian per Triwulan</h6>
            </div>
            <div class="p-4">
                <div class="row g-3">
                    <?php foreach([1,2,3,4] as $tw):
                        $pct = $totalIndikator > 0 ? round($twStats[$tw]/$totalIndikator*100) : 0;
                    ?>
                    <div class="col-md-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span style="font-size:13px;font-weight:600;">TW <?= $tw ?></span>
                            <span style="font-size:12px;color:#6B7280;"><?= $twStats[$tw] ?>/<?= $totalIndikator ?> (<?= $pct ?>%)</span>
                        </div>
                        <div class="progress tw-progress">
                            <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'#059669':($pct>=50?'#F5A623':'#EF4444') ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Per-kelompok Table -->
        <div class="card-box">
            <div class="card-box-header">
                <h6><i class="bi bi-table me-2"></i>Status Pengisian per Kelompok</h6>
                <a href="<?= BASE_URL ?>/admin/monitoring.php" class="btn-migas-outline btn" style="font-size:12px;padding:5px 12px;">
                    Lihat Detail <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Kode Kelompok</th>
                            <th class="text-center">Total Indikator</th>
                            <th class="text-center">Terisi</th>
                            <th class="text-center">Belum</th>
                            <th>Progress</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelompokData as $row):
                            $pct = $row['total'] > 0 ? round($row['terisi']/$row['total']*100) : 0;
                            $belum = $row['total'] - $row['terisi'];
                        ?>
                        <tr>
                            <td><span class="badge-pic"><?= htmlspecialchars($row['pic']) ?></span></td>
                            <td class="text-center fw-bold"><?= $row['total'] ?></td>
                            <td class="text-center text-success fw-bold"><?= $row['terisi'] ?></td>
                            <td class="text-center text-danger fw-bold"><?= $belum ?></td>
                            <td style="min-width:140px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1 tw-progress">
                                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=80?'#059669':($pct>=50?'#F5A623':'#EF4444') ?>"></div>
                                    </div>
                                    <small style="font-size:11px;font-weight:700;width:32px;"><?= $pct ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/admin/indikator.php?pic=<?= urlencode($row['pic']) ?>"
                                   class="btn btn-sm btn-outline-dark" style="font-size:11.5px;border-radius:6px;">
                                    <i class="bi bi-eye"></i> Lihat
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
