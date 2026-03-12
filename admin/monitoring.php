<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$pageTitle = 'Monitoring Capaian';

// Summary per PIC
$summary = $db->query("
    SELECT i.pic,
           COUNT(*) total,
           SUM(CASE WHEN r.tw1 IS NOT NULL THEN 1 ELSE 0 END) tw1,
           SUM(CASE WHEN r.tw2 IS NOT NULL THEN 1 ELSE 0 END) tw2,
           SUM(CASE WHEN r.tw3 IS NOT NULL THEN 1 ELSE 0 END) tw3,
           SUM(CASE WHEN r.tw4 IS NOT NULL THEN 1 ELSE 0 END) tw4,
           SUM(CASE WHEN r.id IS NOT NULL THEN 1 ELSE 0 END) terisi
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id=i.id AND r.tahun_id=i.tahun_id
    WHERE i.tahun_id = $tid
    GROUP BY i.pic
    ORDER BY i.pic
")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-bar-chart-line me-2"></i>Monitoring Capaian</span>
        <a href="<?= BASE_URL ?>/admin/export.php" class="btn-migas btn" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>

    <div class="page-content">
        <div class="card-box">
            <div class="card-box-header">
                <h6><i class="bi bi-table me-2"></i>Rekapitulasi Pengisian per Kelompok — Tahun <?= $tahun['tahun'] ?? '-' ?></h6>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Kelompok (PIC)</th>
                            <th class="text-center">Total</th>
                            <th class="text-center">TW I</th>
                            <th class="text-center">TW II</th>
                            <th class="text-center">TW III</th>
                            <th class="text-center">TW IV</th>
                            <th class="text-center">Terisi</th>
                            <th>Progress</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summary as $row):
                            $pct = $row['total'] > 0 ? round($row['terisi']/$row['total']*100) : 0;
                            $color = $pct>=80 ? '#059669' : ($pct>=50 ? '#F5A623' : '#EF4444');
                        ?>
                        <tr>
                            <td><span class="badge-pic"><?= htmlspecialchars($row['pic']) ?></span></td>
                            <td class="text-center fw-bold"><?= $row['total'] ?></td>
                            <?php foreach(['tw1','tw2','tw3','tw4'] as $tw): ?>
                                <td class="text-center">
                                    <span style="font-size:12px;font-weight:600;color:<?= $row[$tw]>0?'#059669':'#9CA3AF' ?>">
                                        <?= $row[$tw] ?>
                                    </span>
                                    <span style="font-size:10px;color:#9CA3AF;">/<?= $row['total'] ?></span>
                                </td>
                            <?php endforeach; ?>
                            <td class="text-center fw-bold" style="color:<?= $color ?>;"><?= $row['terisi'] ?></td>
                            <td style="min-width:160px;">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress flex-grow-1 tw-progress">
                                        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
                                    </div>
                                    <small style="font-size:11px;font-weight:700;width:34px;color:<?= $color ?>"><?= $pct ?>%</small>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="<?= BASE_URL ?>/admin/indikator.php?pic=<?= urlencode($row['pic']) ?>"
                                   class="btn btn-sm btn-outline-dark" style="font-size:11.5px;border-radius:6px;">
                                    <i class="bi bi-eye"></i> Detail
                                </a>
                                <a href="<?= BASE_URL ?>/admin/export.php?pic=<?= urlencode($row['pic']) ?>"
                                   class="btn btn-sm btn-outline-success ms-1" style="font-size:11.5px;border-radius:6px;">
                                    <i class="bi bi-download"></i>
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
