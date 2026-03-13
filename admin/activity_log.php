<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db        = getDB();
$pageTitle = 'Log Aktivitas';

// Filter
$filterUser   = $_GET['user']   ?? '';
$filterModule = $_GET['module'] ?? '';
$filterDate   = $_GET['date']   ?? '';
$filterRole   = $_GET['role']   ?? '';

$where  = ['1=1'];
$params = [];
$types  = '';

if ($filterUser) {
    $where[]  = 'l.username LIKE ?';
    $params[] = "%$filterUser%";
    $types   .= 's';
}
if ($filterModule) {
    $where[]  = 'l.module = ?';
    $params[] = $filterModule;
    $types   .= 's';
}
if ($filterDate) {
    $where[]  = 'DATE(l.created_at) = ?';
    $params[] = $filterDate;
    $types   .= 's';
}
if ($filterRole) {
    $where[]  = 'l.role = ?';
    $params[] = $filterRole;
    $types   .= 's';
}

$whereSQL = implode(' AND ', $where);

// Pagination
$perPage  = 50;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) c FROM activity_log l WHERE $whereSQL");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows  = $countStmt->get_result()->fetch_assoc()['c'];
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $db->prepare("
    SELECT l.*, u.nama_lengkap
    FROM activity_log l
    LEFT JOIN users u ON u.id = l.user_id
    WHERE $whereSQL
    ORDER BY l.created_at DESC
    LIMIT $perPage OFFSET $offset
");
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Daftar modul untuk filter dropdown
$modules = $db->query("SELECT DISTINCT module FROM activity_log WHERE module != '' ORDER BY module")
              ->fetch_all(MYSQLI_ASSOC);

// Action badge color mapping
function actionColor(string $action): string {
    $action = strtoupper($action);
    if (str_contains($action, 'LOGIN'))   return '#059669';
    if (str_contains($action, 'LOGOUT'))  return '#6B7280';
    if (str_contains($action, 'HAPUS') || str_contains($action, 'DELETE')) return '#EF4444';
    if (str_contains($action, 'SIMPAN') || str_contains($action, 'INSERT') || str_contains($action, 'TAMBAH')) return '#1D4ED8';
    if (str_contains($action, 'EDIT') || str_contains($action, 'UPDATE'))  return '#F59E0B';
    if (str_contains($action, 'IMPORT')) return '#7C3AED';
    if (str_contains($action, 'EXPORT')) return '#0891B2';
    return '#374151';
}

// Handle Export Excel — harus sebelum include header agar bisa kirim header HTTP
if (isset($_GET['export'])) {
    $stmtExp = $db->prepare("
        SELECT l.created_at, l.username, l.role, l.action, l.description, l.module, l.ip_address
        FROM activity_log l WHERE $whereSQL ORDER BY l.created_at DESC
    ");
    if ($params) $stmtExp->bind_param($types, ...$params);
    $stmtExp->execute();
    $expRows = $stmtExp->get_result()->fetch_all(MYSQLI_ASSOC);

    $filename = 'log_aktivitas_' . date('Ymd_His') . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    ?>
    <html><head><meta charset="UTF-8">
    <style>
        body{font-family:Arial,sans-serif;font-size:9pt;}
        table{border-collapse:collapse;width:100%;}
        th{background:#1A1A1A;color:#F5A623;border:1px solid #555;padding:6px 8px;text-align:center;font-weight:bold;}
        td{border:1px solid #ccc;padding:5px 7px;vertical-align:top;font-size:8.5pt;}
        tr:nth-child(even) td{background:#FAFAFA;}
        h2{margin-bottom:4px;}
    </style>
    </head><body>
    <h2>Log Aktivitas — Ditjen Migas</h2>
    <p style="font-size:9pt;color:#555;margin-bottom:10px;">Dicetak: <?= date('d/m/Y H:i') ?></p>
    <table>
    <thead>
        <tr>
            <th>No</th><th>Waktu</th><th>Username</th><th>Role</th>
            <th>Aksi</th><th>Keterangan</th><th>Modul</th><th>IP Address</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($expRows as $i => $row): ?>
    <tr>
        <td style="text-align:center;"><?= $i+1 ?></td>
        <td style="white-space:nowrap;"><?= htmlspecialchars($row['created_at']) ?></td>
        <td><b><?= htmlspecialchars($row['username']) ?></b></td>
        <td style="text-align:center;"><?= htmlspecialchars($row['role']) ?></td>
        <td style="text-align:center;font-weight:bold;"><?= htmlspecialchars($row['action']) ?></td>
        <td><?= htmlspecialchars($row['description']) ?></td>
        <td style="text-align:center;"><?= htmlspecialchars($row['module']) ?></td>
        <td style="font-family:monospace;"><?= htmlspecialchars($row['ip_address']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    </table>
    </body></html>
    <?php
    exit;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-clock-history me-2"></i>Log Aktivitas</span>
        <a href="?<?= http_build_query(array_merge($_GET, ['export' => '1'])) ?>"
           class="btn-migas-outline btn" style="font-size:12px;padding:5px 12px;">
            <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
        </a>
    </div>

    <?php // export handled above ?>

    <div class="page-content">

        <!-- Filter -->
        <div class="card-box p-3 mb-3">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;font-weight:600;">Cari Username</label>
                    <input type="text" name="user" class="form-control form-control-sm"
                           placeholder="Ketik username..." value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px;font-weight:600;">Role</label>
                    <select name="role" class="form-select form-select-sm">
                        <option value="">Semua Role</option>
                        <option value="admin"  <?= $filterRole==='admin' ?'selected':'' ?>>Admin</option>
                        <option value="user"   <?= $filterRole==='user'  ?'selected':'' ?>>User</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px;font-weight:600;">Modul</label>
                    <select name="module" class="form-select form-select-sm">
                        <option value="">Semua Modul</option>
                        <?php foreach ($modules as $m): ?>
                        <option value="<?= htmlspecialchars($m['module']) ?>"
                                <?= $filterModule===$m['module']?'selected':'' ?>>
                            <?= htmlspecialchars(ucfirst($m['module'])) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="font-size:12px;font-weight:600;">Tanggal</label>
                    <input type="date" name="date" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn-migas btn" style="font-size:12px;padding:5px 14px;">
                        <i class="bi bi-search me-1"></i>Filter
                    </button>
                    <a href="?" class="btn-migas-outline btn" style="font-size:12px;padding:5px 14px;">
                        <i class="bi bi-x-circle me-1"></i>Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Stats -->
        <div class="row g-3 mb-3">
            <?php
            $statsData = [
                ['label' => 'Total Log',    'icon' => 'clock-history',    'color' => '#1A1A1A',
                 'val' => $db->query("SELECT COUNT(*) c FROM activity_log")->fetch_assoc()['c']],
                ['label' => 'Hari Ini',     'icon' => 'calendar-check',   'color' => '#1D4ED8',
                 'val' => $db->query("SELECT COUNT(*) c FROM activity_log WHERE DATE(created_at)=CURDATE()")->fetch_assoc()['c']],
                ['label' => 'Login',        'icon' => 'box-arrow-in-right','color' => '#059669',
                 'val' => $db->query("SELECT COUNT(*) c FROM activity_log WHERE action='LOGIN'")->fetch_assoc()['c']],
                ['label' => 'Aksi Hapus',   'icon' => 'trash',             'color' => '#EF4444',
                 'val' => $db->query("SELECT COUNT(*) c FROM activity_log WHERE action LIKE '%HAPUS%'")->fetch_assoc()['c']],
            ];
            foreach ($statsData as $s):
            ?>
            <div class="col-md-3">
                <div class="card-box p-3 d-flex align-items-center gap-3">
                    <div style="width:40px;height:40px;border-radius:10px;background:<?= $s['color'] ?>22;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-<?= $s['icon'] ?>" style="font-size:18px;color:<?= $s['color'] ?>;"></i>
                    </div>
                    <div>
                        <div style="font-size:20px;font-weight:800;color:#1A1A1A;line-height:1;"><?= number_format($s['val']) ?></div>
                        <div style="font-size:11px;color:#6B7280;margin-top:2px;"><?= $s['label'] ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Tabel Log -->
        <div class="card-box">
            <div class="card-box-header d-flex justify-content-between align-items-center">
                <h6><i class="bi bi-clock-history me-2"></i>Riwayat Aktivitas</h6>
                <span style="font-size:12px;color:#6B7280;">
                    <?= number_format($totalRows) ?> data
                    <?= $filterUser||$filterModule||$filterDate||$filterRole ? '(difilter)' : '' ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table data-table mb-0" style="font-size:12.5px;">
                    <thead>
                        <tr>
                            <th style="width:140px;">Waktu</th>
                            <th style="width:120px;">Username</th>
                            <th style="width:60px;">Role</th>
                            <th style="width:130px;">Aksi</th>
                            <th>Keterangan</th>
                            <th style="width:90px;">Modul</th>
                            <th style="width:110px;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="7" class="text-center" style="padding:32px;color:#9CA3AF;">
                                <i class="bi bi-inbox" style="font-size:28px;display:block;margin-bottom:8px;"></i>
                                Belum ada log aktivitas
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($logs as $log):
                            $color = actionColor($log['action']);
                        ?>
                        <tr>
                            <td style="white-space:nowrap;color:#6B7280;font-size:11.5px;">
                                <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                            </td>
                            <td>
                                <div style="font-weight:700;color:#1A1A1A;"><?= htmlspecialchars($log['username']) ?></div>
                                <?php if ($log['nama_lengkap']): ?>
                                <div style="font-size:11px;color:#9CA3AF;"><?= htmlspecialchars($log['nama_lengkap']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background:<?= $log['role']==='admin'?'#1A1A1A':'#EFF6FF' ?>;color:<?= $log['role']==='admin'?'#F5A623':'#1D4ED8' ?>;border-radius:4px;padding:2px 7px;font-size:10.5px;font-weight:700;">
                                    <?= ucfirst($log['role'] ?? '-') ?>
                                </span>
                            </td>
                            <td>
                                <span style="background:<?= $color ?>18;color:<?= $color ?>;border:1px solid <?= $color ?>44;border-radius:4px;padding:2px 8px;font-size:10.5px;font-weight:700;white-space:nowrap;">
                                    <?= htmlspecialchars($log['action']) ?>
                                </span>
                            </td>
                            <td style="color:#374151;line-height:1.4;">
                                <?= htmlspecialchars($log['description'] ?: '—') ?>
                            </td>
                            <td>
                                <?php if ($log['module']): ?>
                                <span style="background:#F3F4F6;color:#374151;border-radius:4px;padding:2px 7px;font-size:11px;">
                                    <?= htmlspecialchars(ucfirst($log['module'])) ?>
                                </span>
                                <?php else: ?>
                                <span style="color:#D1D5DB;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:11px;color:#6B7280;font-family:monospace;">
                                <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center p-3" style="border-top:1px solid #E5E7EB;">
                <div style="font-size:12px;color:#6B7280;">
                    Halaman <?= $page ?> dari <?= $totalPages ?>
                </div>
                <div class="d-flex gap-1">
                    <?php
                    $baseUrl = '?' . http_build_query(array_merge($_GET, ['page' => '']));
                    for ($p = 1; $p <= $totalPages; $p++):
                        if ($totalPages > 10 && abs($p - $page) > 2 && $p !== 1 && $p !== $totalPages) {
                            if ($p === 2 || $p === $totalPages - 1) echo '<span style="padding:4px 6px;color:#9CA3AF;">...</span>';
                            continue;
                        }
                    ?>
                    <a href="<?= $baseUrl . $p ?>"
                       style="padding:4px 10px;border-radius:5px;font-size:12px;font-weight:<?= $p===$page?'700':'400' ?>;
                              background:<?= $p===$page?'#F5A623':'#F9FAFB' ?>;color:<?= $p===$page?'#1A1A1A':'#374151' ?>;
                              border:1px solid <?= $p===$page?'#F5A623':'#E5E7EB' ?>;text-decoration:none;">
                        <?= $p ?>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>