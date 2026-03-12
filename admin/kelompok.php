<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db = getDB();
$pageTitle = 'Manajemen Kelompok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $kode = strtoupper(trim($_POST['kode']));
        $nama = trim($_POST['nama']);
        $stmt = $db->prepare("INSERT IGNORE INTO kelompok (kode,nama) VALUES (?,?)");
        $stmt->bind_param('ss', $kode, $nama);
        if ($stmt->execute() && $stmt->affected_rows) flash('success', "Kelompok $kode berhasil ditambahkan.");
        else flash('error', 'Kode kelompok sudah ada.');
    }

    if ($action === 'delete') {
        $kode = $_POST['kode'];
        $stmt = $db->prepare("DELETE FROM kelompok WHERE kode=?");
        $stmt->bind_param('s', $kode);
        $stmt->execute();
        flash('success', "Kelompok $kode dihapus.");
    }

    redirect(BASE_URL . '/admin/kelompok.php');
}

$kelompoks = $db->query("SELECT k.*, COUNT(DISTINCT i.id) AS jml_indikator FROM kelompok k LEFT JOIN indikator i ON i.pic=k.kode GROUP BY k.id ORDER BY k.kode")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-diagram-3 me-2"></i>Manajemen Kelompok</span>
        <button class="btn-migas btn" data-bs-toggle="modal" data-bs-target="#addModal" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-plus-lg me-1"></i>Tambah Kelompok
        </button>
    </div>

    <div class="page-content">
        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
        <?php endif; ?>

        <div class="card-box">
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Kode</th>
                            <th>Nama Kelompok</th>
                            <th class="text-center">Jumlah Indikator</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kelompoks as $i => $k): ?>
                        <tr>
                            <td style="color:#9CA3AF;font-size:12px;"><?= $i+1 ?></td>
                            <td><span class="badge-pic"><?= htmlspecialchars($k['kode']) ?></span></td>
                            <td style="font-size:13px;"><?= htmlspecialchars($k['nama']) ?></td>
                            <td class="text-center fw-bold"><?= $k['jml_indikator'] ?></td>
                            <td class="text-center">
                                <form method="POST" class="d-inline" onsubmit="return confirm('Hapus kelompok <?= $k['kode'] ?>?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="kode" value="<?= $k['kode'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="font-size:11px;border-radius:6px;">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                <h5 class="modal-title fw-bold" style="font-size:15px;">Tambah Kelompok</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label">Kode Kelompok</label>
                        <input type="text" name="kode" class="form-control" placeholder="DMEE" maxlength="10" required style="text-transform:uppercase;">
                        <div class="form-text">3 huruf = bisa lihat sub-kelompok. 4+ huruf = khusus kelompok itu saja.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kelompok</label>
                        <input type="text" name="nama" class="form-control" placeholder="Eksplorasi Minyak" required>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn-migas-outline btn" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-migas btn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
