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
        flash('success', "Status TW $tw berhasil diperbarui.");
    }

    if ($action === 'add_tahun') {
        $tahun = (int)$_POST['tahun'];
        $stmt  = $db->prepare("INSERT IGNORE INTO tahun_anggaran (tahun) VALUES (?)");
        $stmt->bind_param('i', $tahun);
        $stmt->execute();
        flash('success', "Tahun $tahun berhasil ditambahkan.");
    }

    if ($action === 'set_aktif') {
        $tid = (int)$_POST['tahun_id'];
        $db->query("UPDATE tahun_anggaran SET is_aktif=0");
        $db->query("UPDATE tahun_anggaran SET is_aktif=1 WHERE id=$tid");
        flash('success', 'Tahun aktif berhasil diperbarui.');
    }

    redirect(BASE_URL . '/admin/periode.php');
}

$tahunan = $db->query("SELECT * FROM tahun_anggaran ORDER BY tahun DESC")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-calendar3 me-2"></i>Manajemen Periode / TW</span>
    </div>

    <div class="page-content">
        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
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
                        <?php foreach ($tahunan as $t): ?>
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
                            <?php foreach([1,2,3,4] as $tw): ?>
                            <td class="text-center">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_tw">
                                    <input type="hidden" name="tahun_id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="tw" value="<?= $tw ?>">
                                    <input type="hidden" name="val" value="<?= $t["tw{$tw}_open"] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $t["tw{$tw}_open"] ? 'btn-success' : 'btn-outline-danger' ?>" style="font-size:11.5px;min-width:64px;">
                                        <?= $t["tw{$tw}_open"] ? '<i class="bi bi-unlock"></i> Buka' : '<i class="bi bi-lock"></i> Tutup' ?>
                                    </button>
                                </form>
                            </td>
                            <?php endforeach; ?>
                            <td class="text-center">—</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
