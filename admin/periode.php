<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db = getDB();
$pageTitle = 'Manajemen Periode';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_tw') {
        $tid = (int)$_POST['tahun_id'];
        $tw  = (int)$_POST['tw'];
        $val = (int)$_POST['val'];
        $db->query("UPDATE tahun_anggaran SET tw{$tw}_open=$val WHERE id=$tid");
        $status = $val ? 'dibuka' : 'dikunci';
        logActivity('TOGGLE_TW', "TW $tw tahun {$tahun['tahun']} berhasil $status", 'periode');
        flash('success', "Periode TW $tw berhasil $status.");
    }

    if ($action === 'add_tahun') {
        $tahun = (int)$_POST['tahun'];
        $stmt  = $db->prepare("INSERT IGNORE INTO tahun_anggaran (tahun) VALUES (?)");
        $stmt->bind_param('i', $tahun);
        $stmt->execute();
        logActivity('TAMBAH_TAHUN', "Tambah tahun anggaran: $tahun", 'periode');
        flash('success', "Tahun $tahun berhasil ditambahkan.");
    }

    if ($action === 'set_aktif') {
        $tid = (int)$_POST['tahun_id'];
        $db->query("UPDATE tahun_anggaran SET is_aktif=0");
        $db->query("UPDATE tahun_anggaran SET is_aktif=1 WHERE id=$tid");
        $tRow = $db->query("SELECT tahun FROM tahun_anggaran WHERE id=$tid")->fetch_assoc();
        logActivity('SET_AKTIF_TAHUN', "Set tahun aktif: " . ($tRow['tahun'] ?? $tid), 'periode');
        flash('success', 'Tahun aktif berhasil diperbarui.');
    }

    if ($action === 'delete_tahun') {
        $tid = (int)$_POST['tahun_id'];
        // Tidak boleh hapus tahun yang sedang aktif
        $cek = $db->query("SELECT is_aktif FROM tahun_anggaran WHERE id=$tid")->fetch_assoc();
        if ($cek && $cek['is_aktif']) {
            flash('error', 'Tahun aktif tidak dapat dihapus.');
        } else {
            $tRow = $db->query("SELECT tahun FROM tahun_anggaran WHERE id=$tid")->fetch_assoc();
            $db->query("DELETE FROM tahun_anggaran WHERE id=$tid");
            logActivity('HAPUS_TAHUN', "Hapus tahun anggaran: " . ($tRow['tahun'] ?? $tid), 'periode');
            flash('success', 'Tahun anggaran berhasil dihapus.');
        }
    }

    redirect(BASE_URL . '/admin/periode.php');
}

$tahunan = $db->query("SELECT * FROM tahun_anggaran ORDER BY tahun DESC")->fetch_all(MYSQLI_ASSOC);

// Hitung jumlah total indikator & terisi per tahun per TW
$statsPerTahun = [];
foreach ($tahunan as $t) {
    $tid = $t['id'];
    $stats = [];
    foreach ([1,2,3,4] as $tw) {
        $res = $db->query("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN r.tw$tw IS NOT NULL AND r.tw$tw != '' THEN 1 ELSE 0 END) AS terisi
            FROM indikator i
            LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
            WHERE i.tahun_id = $tid
        ")->fetch_assoc();
        $stats[$tw] = $res;
    }
    $statsPerTahun[$tid] = $stats;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-calendar3 me-2"></i>Manajemen Periode / TW</span>
    </div>

    <div class="page-content">
        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <?php $err = getFlash('error'); if ($err): ?>
            <div class="alert alert-danger alert-auto-hide"><?= htmlspecialchars($err) ?></div>
        <?php endif; ?>

        <!-- Add Tahun -->
        <div class="card-box p-4 mb-4" style="max-width:400px;">
            <h6 class="fw-700 mb-3">Tambah Tahun Anggaran</h6>
            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="action" value="add_tahun">
                <input type="number" name="tahun" class="form-control" placeholder="2026" min="2020" max="2040" required>
                <button type="submit" class="btn-migas btn" style="white-space:nowrap;">Tambah</button>
            </form>
        </div>

        <!-- Tahun Table -->
        <div class="card-box">
            <div class="card-box-header">
                <h6><i class="bi bi-calendar3 me-2"></i>Daftar Tahun Anggaran</h6>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>Tahun</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">TW I</th>
                            <th class="text-center">TW II</th>
                            <th class="text-center">TW III</th>
                            <th class="text-center">TW IV</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tahunan as $t):
                            $tid = $t['id'];
                        ?>
                        <tr>
                            <td class="fw-bold" style="font-size:15px;"><?= $t['tahun'] ?></td>
                            <td class="text-center">
                                <?php if ($t['is_aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="set_aktif">
                                        <input type="hidden" name="tahun_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" style="font-size:11px;">Set Aktif</button>
                                    </form>
                                <?php endif; ?>
                            </td>

                            <?php foreach([1,2,3,4] as $tw):
                                $isOpen  = (bool)$t["tw{$tw}_open"];
                                $stat    = $statsPerTahun[$tid][$tw];
                                $total   = (int)$stat['total'];
                                $terisi  = (int)$stat['terisi'];
                                $pct     = $total > 0 ? round($terisi / $total * 100) : 0;
                                $barColor = $pct >= 100 ? '#059669' : ($pct > 0 ? '#F59E0B' : '#E5E7EB');
                            ?>
                            <td class="text-center" style="min-width:130px;">
                                <!-- Tombol Buka / Kunci -->
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_tw">
                                    <input type="hidden" name="tahun_id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="tw" value="<?= $tw ?>">
                                    <input type="hidden" name="val" value="<?= $isOpen ? 0 : 1 ?>">
                                    <button type="submit"
                                            class="btn btn-sm <?= $isOpen ? 'btn-success' : 'btn-outline-danger' ?>"
                                            style="font-size:11.5px;min-width:76px;"
                                            title="<?= $isOpen ? 'Klik untuk mengunci TW '.$tw : 'Klik untuk membuka TW '.$tw ?>">
                                        <?php if ($isOpen): ?>
                                            <i class="bi bi-unlock-fill me-1"></i>Terbuka
                                        <?php else: ?>
                                            <i class="bi bi-lock-fill me-1"></i>Terkunci
                                        <?php endif; ?>
                                    </button>
                                    <?php if ($pct >= 100): ?>
                                    <span style="display:inline-block;margin-left:4px;background:#ECFDF5;color:#059669;border:1.5px solid #6EE7B7;border-radius:20px;padding:2px 8px;font-size:10px;font-weight:700;vertical-align:middle;">
                                        <i class="bi bi-check-circle-fill me-1"></i>Terisi
                                    </span>
                                    <?php elseif ($pct > 0): ?>
                                    <span style="display:inline-block;margin-left:4px;background:#FFFBEB;color:#92400E;border:1.5px solid #FCD34D;border-radius:20px;padding:2px 8px;font-size:10px;font-weight:700;vertical-align:middle;">
                                        <i class="bi bi-hourglass-split me-1"></i>Sebagian
                                    </span>
                                    <?php endif; ?>
                                </form>

                                <!-- Progress terisi -->
                                <?php if ($total > 0): ?>
                                <div class="mt-1">
                                    <div style="font-size:10.5px;color:#6B7280;margin-bottom:2px;">
                                        <?= $terisi ?>/<?= $total ?> terisi
                                        <span style="color:<?= $pct >= 100 ? '#059669' : ($pct > 0 ? '#F59E0B' : '#9CA3AF') ?>;font-weight:700;">
                                            (<?= $pct ?>%)
                                        </span>
                                    </div>
                                    <div style="height:4px;background:#E5E7EB;border-radius:4px;overflow:hidden;">
                                        <div style="width:<?= $pct ?>%;height:100%;background:<?= $barColor ?>;border-radius:4px;transition:.3s;"></div>
                                    </div>
                                </div>
                                <?php else: ?>
                                <div style="font-size:10.5px;color:#D1D5DB;margin-top:4px;">Belum ada indikator</div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>

                            <!-- Aksi: Hapus Tahun -->
                            <td class="text-center">
                                <?php if (!$t['is_aktif']): ?>
                                <form method="POST" class="d-inline"
                                      onsubmit="return confirm('Yakin hapus tahun ' + <?= $t['tahun'] ?> + '? Semua data indikator dan realisasi tahun ini akan ikut terhapus!')">
                                    <input type="hidden" name="action" value="delete_tahun">
                                    <input type="hidden" name="tahun_id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:11px;">
                                        <i class="bi bi-trash me-1"></i>Hapus
                                    </button>
                                </form>
                                <?php else: ?>
                                <span style="font-size:11px;color:#9CA3AF;" title="Tahun aktif tidak dapat dihapus">—</span>
                                <?php endif; ?>
                            </td>

                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-3 d-flex gap-3 flex-wrap" style="font-size:12px;color:#6B7280;">
            <span><i class="bi bi-unlock-fill text-success me-1"></i>Terbuka = user dapat input realisasi</span>
            <span><i class="bi bi-lock-fill text-danger me-1"></i>Terkunci = user tidak dapat mengubah data</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#059669;border-radius:2px;margin-right:4px;"></span>100% terisi</span>
            <span><span style="display:inline-block;width:10px;height:10px;background:#F59E0B;border-radius:2px;margin-right:4px;"></span>Sebagian terisi</span>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>