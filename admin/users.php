<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db = getDB();
$pageTitle = 'Manajemen Pengguna';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username = trim($_POST['username']);
        $nama     = trim($_POST['nama_lengkap']);
        $role     = $_POST['role'];
        $kode     = $role === 'admin' ? null : trim($_POST['kode_kelompok']);
        $pass     = password_hash(trim($_POST['password']), PASSWORD_BCRYPT);

        $stmt = $db->prepare("INSERT INTO users (username,password,nama_lengkap,role,kode_kelompok) VALUES (?,?,?,?,?)");
        $stmt->bind_param('sssss', $username, $pass, $nama, $role, $kode);
        if ($stmt->execute()) flash('success', 'Pengguna berhasil ditambahkan.');
        else flash('error', 'Username sudah digunakan.');
    }

    if ($action === 'toggle') {
        $uid = (int)$_POST['user_id'];
        $db->query("UPDATE users SET is_active = NOT is_active WHERE id=$uid");
        flash('success', 'Status pengguna diperbarui.');
    }

    if ($action === 'reset_pass') {
        $uid  = (int)$_POST['user_id'];
        $pass = password_hash(trim($_POST['new_password']), PASSWORD_BCRYPT);
        $stmt = $db->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param('si', $pass, $uid);
        $stmt->execute();
        flash('success', 'Password berhasil direset.');
    }

    redirect(BASE_URL . '/admin/users.php');
}

$users = $db->query("SELECT u.*, k.nama as nama_kelompok FROM users u LEFT JOIN kelompok k ON k.kode=u.kode_kelompok ORDER BY u.role, u.username")->fetch_all(MYSQLI_ASSOC);
$kelompoks = $db->query("SELECT kode,nama FROM kelompok ORDER BY kode")->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-people me-2"></i>Manajemen Pengguna</span>
        <button class="btn-migas btn" data-bs-toggle="modal" data-bs-target="#addUserModal" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-plus-lg me-1"></i>Tambah Pengguna
        </button>
    </div>

    <div class="page-content">
        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
        <?php endif; ?>
        <?php $err = getFlash('error'); if ($err): ?>
            <div class="alert alert-danger alert-auto-hide"><?= $err ?></div>
        <?php endif; ?>

        <div class="card-box">
            <div class="table-responsive">
                <table class="table data-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Role</th>
                            <th>Kelompok</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td style="color:#9CA3AF;font-size:12px;"><?= $i+1 ?></td>
                            <td><code style="font-size:13px;"><?= htmlspecialchars($u['username']) ?></code></td>
                            <td><?= htmlspecialchars($u['nama_lengkap']) ?></td>
                            <td>
                                <?php if ($u['role']==='admin'): ?>
                                    <span class="badge" style="background:#1A1A1A;color:#F5A623;font-size:11px;">Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary" style="font-size:11px;">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['kode_kelompok']): ?>
                                    <span class="badge-pic"><?= $u['kode_kelompok'] ?></span>
                                <?php else: ?>
                                    <span style="color:#D1D5DB;font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-danger' ?>" style="font-size:11px;">
                                    <?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'danger' : 'success' ?>"
                                                style="font-size:11px;border-radius:6px;">
                                            <?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-secondary" style="font-size:11px;border-radius:6px;"
                                            data-bs-toggle="modal" data-bs-target="#resetPassModal"
                                            data-uid="<?= $u['id'] ?>" data-uname="<?= $u['username'] ?>">
                                        Reset Pass
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                <h5 class="modal-title fw-bold" style="font-size:15px;">Tambah Pengguna</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" id="roleSelect">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6" id="kelompokField">
                            <label class="form-label">Kode Kelompok</label>
                            <select name="kode_kelompok" class="form-select">
                                <option value="">Pilih Kelompok</option>
                                <?php foreach ($kelompoks as $k): ?>
                                    <option value="<?= $k['kode'] ?>"><?= $k['kode'] ?> — <?= $k['nama'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPassModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content" style="border-radius:12px;border:none;">
            <div class="modal-header" style="border-bottom:1px solid #E5E7EB;">
                <h5 class="modal-title fw-bold" style="font-size:15px;">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reset_pass">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-body p-4">
                    <p style="font-size:13px;color:#6B7280;">Reset password untuk: <strong id="resetUsername"></strong></p>
                    <label class="form-label">Password Baru</label>
                    <input type="password" name="new_password" class="form-control" required>
                </div>
                <div class="modal-footer" style="border-top:1px solid #E5E7EB;">
                    <button type="button" class="btn-migas-outline btn" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-migas btn">Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('roleSelect')?.addEventListener('change', function() {
    document.getElementById('kelompokField').style.display = this.value === 'admin' ? 'none' : '';
});

document.getElementById('resetPassModal')?.addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('resetUserId').value = btn.dataset.uid;
    document.getElementById('resetUsername').textContent = btn.dataset.uname;
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
