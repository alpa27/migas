<?php
// includes/sidebar.php
$user = getCurrentUser();
$role = $user['role'];
$kode = $user['kode_kelompok'];
$initials = strtoupper(substr($user['nama'], 0, 2));

$currentFile = basename($_SERVER['PHP_SELF']);
$currentDir  = basename(dirname($_SERVER['PHP_SELF']));

function navLink(string $href, string $icon, string $label, string $currentFile, string $targetFile): string {
    $active = $currentFile === $targetFile ? 'active' : '';
    return "<a href=\"$href\" class=\"$active\"><i class=\"bi bi-$icon\"></i> $label</a>";
}
?>

<div class="sidebar">
    <div class="sidebar-brand">
        <img src="<?= LOGO_URL ?>" alt="Ditjen Migas">
        <div class="sidebar-brand-text">
            <span>Ditjen Migas</span>
            <small>Monitoring Indikator Kinerja</small>
        </div>
    </div>

    <div class="sidebar-nav mt-2">

        <?php if ($role === 'admin'): ?>
            <div class="sidebar-section">Admin</div>
            <?= navLink(BASE_URL.'/admin/dashboard.php',  'speedometer2',  'Dashboard',         $currentFile, 'dashboard.php') ?>
            <?= navLink(BASE_URL.'/admin/indikator.php',  'list-task',     'Semua Indikator',   $currentFile, 'indikator.php') ?>
            <?= navLink(BASE_URL.'/admin/monitoring.php', 'bar-chart-line','Monitoring Capaian',$currentFile, 'monitoring.php') ?>
            <?= navLink(BASE_URL.'/admin/export.php',     'file-earmark-excel', 'Export Excel', $currentFile, 'export.php') ?>

            <div class="sidebar-section">Manajemen</div>
            <?= navLink(BASE_URL.'/admin/users.php',      'people',        'Pengguna',          $currentFile, 'users.php') ?>
            <?= navLink(BASE_URL.'/admin/kelompok.php',   'diagram-3',     'Kelompok',          $currentFile, 'kelompok.php') ?>
            <?= navLink(BASE_URL.'/admin/periode.php',    'calendar3',     'Periode / TW',      $currentFile, 'periode.php') ?>
            <?= navLink(BASE_URL.'/admin/import.php',     'upload',        'Import Indikator',  $currentFile, 'import.php') ?>

            <div class="sidebar-section">Sistem</div>
            <?= navLink(BASE_URL.'/admin/activity_log.php', 'clock-history', 'Log Aktivitas',     $currentFile, 'activity_log.php') ?>

        <?php else: ?>
            <div class="sidebar-section">Menu</div>
            <?= navLink(BASE_URL.'/user/dashboard.php',  'speedometer2',  'Dashboard',     $currentFile, 'dashboard.php') ?>
            <?= navLink(BASE_URL.'/user/indikator.php',  'list-task',     'Indikator Saya',$currentFile, 'indikator.php') ?>
            <?= navLink(BASE_URL.'/user/realisasi.php',  'pencil-square', 'Input Realisasi',$currentFile,'realisasi.php') ?>
        <?php endif; ?>

    </div>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar"><?= $initials ?></div>
            <div>
                <div class="uname"><?= htmlspecialchars($user['nama']) ?></div>
                <div class="urole">
                    <?= $role === 'admin' ? 'Administrator' : htmlspecialchars($kode) ?>
                </div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/logout.php" class="d-flex align-items-center gap-2 mt-2 px-2 py-2 text-decoration-none" style="color:rgba(255,255,255,.45);font-size:12.5px;border-radius:6px;transition:.2s;" onmouseover="this.style.color='#F87171'" onmouseout="this.style.color='rgba(255,255,255,.45)'">
            <i class="bi bi-box-arrow-right"></i> Keluar
        </a>
    </div>
</div>