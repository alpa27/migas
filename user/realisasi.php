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
$pageTitle = 'Input Realisasi';

$specificId = (int)($_GET['id'] ?? 0);
$indikator  = null;
$realisasi  = [];
$errors     = [];

// Load indikator yang dipilih
if ($specificId) {
    [$condition, $param] = buildPicCondition($kode, 'i');
    $stmt = $db->prepare("SELECT * FROM indikator i WHERE i.id = ? AND i.tahun_id = ? AND $condition");
    $stmt->bind_param('iss', $specificId, $tid, $param);
    $stmt->execute();
    $indikator = $stmt->get_result()->fetch_assoc();
    if (!$indikator) redirect(BASE_URL . '/user/indikator.php');

    $stmt2 = $db->prepare("SELECT * FROM realisasi WHERE indikator_id = ? AND tahun_id = ?");
    $stmt2->bind_param('ii', $specificId, $tid);
    $stmt2->execute();
    $realisasi = $stmt2->get_result()->fetch_assoc() ?? [];
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iid = (int)($_POST['indikator_id'] ?? 0);
    if (!$iid) redirect(BASE_URL . '/user/realisasi.php');

    // Verify ownership
    [$condition, $param] = buildPicCondition($kode, 'i');
    $sv = $db->prepare("SELECT id FROM indikator i WHERE i.id = ? AND i.tahun_id = ? AND $condition");
    $sv->bind_param('iss', $iid, $tid, $param);
    $sv->execute();
    if (!$sv->get_result()->num_rows) redirect(BASE_URL . '/user/indikator.php');

    // Validasi:
    // - Setiap TW boleh diisi atau dibiarkan kosong (tidak harus semua)
    // - Jika salah satu kolom di TW diisi, maka SEMUA kolom TW itu wajib lengkap
    // - Minimal 1 TW harus diisi
    $adaYangDiisi = false;
    foreach ([1,2,3,4] as $tw) {
        if (!isTwOpen($tahun, $tw)) continue;
        $val  = trim($_POST["tw$tw"] ?? '');
        $link = trim($_POST["link_tw$tw"] ?? '');
        $eval = trim($_POST["evaluasi_tw$tw"] ?? '');
        $rtl  = trim($_POST["tindak_lanjut_tw$tw"] ?? '');

        // Cek apakah ada salah satu kolom TW ini yang diisi
        $adaIsiDiTW = ($val !== '' || $link !== '' || $eval !== '' || $rtl !== '');

        if ($adaIsiDiTW) {
            $adaYangDiisi = true;
            if ($val  === '') $errors[] = "Realisasi TW $tw wajib diisi.";
            if ($link === '') $errors[] = "Link Data Dukung TW $tw wajib diisi.";
            if ($eval === '') $errors[] = "Evaluasi TW $tw wajib diisi.";
            if ($rtl  === '') $errors[] = "Rencana Tindak Lanjut TW $tw wajib diisi.";
        }
    }
    if (!$adaYangDiisi) $errors[] = "Minimal satu Triwulan harus diisi.";

    if (empty($errors)) {
        $uid = $user['id'];

        // Ambil data lama dari DB untuk menjaga TW yang tidak disubmit
        $existingStmt = $db->prepare("SELECT * FROM realisasi WHERE indikator_id = ? AND tahun_id = ?");
        $existingStmt->bind_param('ii', $iid, $tid);
        $existingStmt->execute();
        $existing = $existingStmt->get_result()->fetch_assoc();

        // Bangun data: TW yang periodenya terbuka -> ambil dari POST, sisanya -> pertahankan dari DB
        $d = [];
        foreach ([1,2,3,4] as $tw) {
            $open    = isTwOpen($tahun, $tw);
            $postVal = trim($_POST["tw$tw"] ?? '');
            if ($open && $postVal !== '') {
                // TW ini disubmit user, pakai data POST
                $d["tw$tw"]               = (float)str_replace(',', '.', $postVal);
                $d["link_tw$tw"]          = trim($_POST["link_tw$tw"]          ?? '');
                $d["evaluasi_tw$tw"]      = trim($_POST["evaluasi_tw$tw"]      ?? '');
                $d["tindak_lanjut_tw$tw"] = trim($_POST["tindak_lanjut_tw$tw"] ?? '');
            } else {
                // TW ini tidak disubmit, pertahankan data lama dari DB
                $d["tw$tw"]               = $existing["tw$tw"]               ?? null;
                $d["link_tw$tw"]          = $existing["link_tw$tw"]          ?? null;
                $d["evaluasi_tw$tw"]      = $existing["evaluasi_tw$tw"]      ?? null;
                $d["tindak_lanjut_tw$tw"] = $existing["tindak_lanjut_tw$tw"] ?? null;
            }
        }

        if ($existing) {
            $upd = $db->prepare("
                UPDATE realisasi SET
                    tw1=?, tw2=?, tw3=?, tw4=?,
                    link_tw1=?, evaluasi_tw1=?, tindak_lanjut_tw1=?,
                    link_tw2=?, evaluasi_tw2=?, tindak_lanjut_tw2=?,
                    link_tw3=?, evaluasi_tw3=?, tindak_lanjut_tw3=?,
                    link_tw4=?, evaluasi_tw4=?, tindak_lanjut_tw4=?,
                    updated_by=?
                WHERE indikator_id=? AND tahun_id=?
            ");
            $upd->bind_param(
                'ddddssssssssssssiii',
                $d['tw1'], $d['tw2'], $d['tw3'], $d['tw4'],
                $d['link_tw1'], $d['evaluasi_tw1'], $d['tindak_lanjut_tw1'],
                $d['link_tw2'], $d['evaluasi_tw2'], $d['tindak_lanjut_tw2'],
                $d['link_tw3'], $d['evaluasi_tw3'], $d['tindak_lanjut_tw3'],
                $d['link_tw4'], $d['evaluasi_tw4'], $d['tindak_lanjut_tw4'],
                $uid, $iid, $tid
            );
            if (!$upd->execute()) {
                $errors[] = "Gagal update: " . $upd->error;
            }
        } else {
            $ins = $db->prepare("
                INSERT INTO realisasi
                    (indikator_id, tahun_id,
                     tw1, tw2, tw3, tw4,
                     link_tw1, evaluasi_tw1, tindak_lanjut_tw1,
                     link_tw2, evaluasi_tw2, tindak_lanjut_tw2,
                     link_tw3, evaluasi_tw3, tindak_lanjut_tw3,
                     link_tw4, evaluasi_tw4, tindak_lanjut_tw4,
                     updated_by)
                VALUES (?,?, ?,?,?,?, ?,?,?, ?,?,?, ?,?,?, ?,?,?, ?)
            ");
            $ins->bind_param(
                'iiddddssssssssssssi',
                $iid, $tid,
                $d['tw1'], $d['tw2'], $d['tw3'], $d['tw4'],
                $d['link_tw1'], $d['evaluasi_tw1'], $d['tindak_lanjut_tw1'],
                $d['link_tw2'], $d['evaluasi_tw2'], $d['tindak_lanjut_tw2'],
                $d['link_tw3'], $d['evaluasi_tw3'], $d['tindak_lanjut_tw3'],
                $d['link_tw4'], $d['evaluasi_tw4'], $d['tindak_lanjut_tw4'],
                $uid
            );
            if (!$ins->execute()) {
                $errors[] = "Gagal insert: " . $ins->error;
            }
        }

        if (empty($errors)) {
            // Cari TW berikutnya yang belum terisi untuk dijadikan tab aktif
            $nextTw = 0;
            foreach ([1,2,3,4] as $twNext) {
                if (!isTwOpen($tahun, $twNext)) continue;
                $chkVal = $d["tw$twNext"] ?? null;
                if ($chkVal === null || $chkVal === '') {
                    $nextTw = $twNext;
                    break;
                }
            }
            // Ambil nama indikator untuk log
            $namaInd = $db->query("SELECT nama_indikator FROM indikator WHERE id=$iid")->fetch_assoc();
            $twDisimpan = array_filter([1,2,3,4], fn($x) => isTwOpen($tahun, $x) && trim($_POST["tw$x"] ?? '') !== '');
            $twList = implode(',', array_map(fn($x) => "TW$x", $twDisimpan));
            logActivity('SIMPAN_REALISASI', "Simpan realisasi [$twList]: " . ($namaInd['nama_indikator'] ?? "Indikator #$iid"), 'realisasi');
            flash('success', 'Realisasi berhasil disimpan.');
            $redirectUrl = BASE_URL . '/user/realisasi.php?id=' . $iid;
            if ($nextTw) $redirectUrl .= '&open_tw=' . $nextTw;
            redirect($redirectUrl);
        }
    }

    // Jika error, reload data form
    $specificId = $iid;
    [$condition, $param] = buildPicCondition($kode, 'i');
    $stmt = $db->prepare("SELECT * FROM indikator i WHERE i.id = ? AND i.tahun_id = ? AND $condition");
    $stmt->bind_param('iss', $iid, $tid, $param);
    $stmt->execute();
    $indikator = $stmt->get_result()->fetch_assoc();

    $stmt2 = $db->prepare("SELECT * FROM realisasi WHERE indikator_id = ? AND tahun_id = ?");
    $stmt2->bind_param('ii', $iid, $tid);
    $stmt2->execute();
    $realisasi = $stmt2->get_result()->fetch_assoc() ?? [];
}

// Load semua indikator untuk sidebar kiri
[$condition, $param] = buildPicCondition($kode, 'i');
$stmt = $db->prepare("
    SELECT i.*, r.tw1, r.tw2, r.tw3, r.tw4, r.id AS real_id
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id = i.id AND r.tahun_id = i.tahun_id
    WHERE i.tahun_id = $tid AND $condition
    ORDER BY i.urutan
");
$stmt->bind_param('s', $param);
$stmt->execute();
$allIndikator = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-pencil-square me-2"></i>Input Realisasi</span>
        <a href="<?= BASE_URL ?>/user/indikator.php" class="btn-migas-outline btn" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="page-content">
        <?php $msg = getFlash('success'); if ($msg): ?>
            <div class="alert alert-success alert-auto-hide"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="row g-3">

            <!-- Kiri: Daftar Indikator -->
            <div class="col-lg-4">
                <div class="card-box" style="max-height:82vh;overflow-y:auto;">
                    <div class="card-box-header">
                        <h6 style="font-size:13px;"><i class="bi bi-list-task me-2"></i>Pilih Indikator</h6>
                    </div>
                    <div style="padding:8px;">
                        <?php foreach ($allIndikator as $row):
                            $isFilled  = $row['tw1']!==null && $row['tw2']!==null && $row['tw3']!==null && $row['tw4']!==null;
                            $isPartial = !$isFilled && ($row['tw1']!==null || $row['tw2']!==null || $row['tw3']!==null || $row['tw4']!==null);
                            $isActive  = $specificId == $row['id'];
                            $lvl       = str_replace('-','',strtolower($row['leveling']));
                            $dotColor  = $isFilled ? '#059669' : ($isPartial ? '#F59E0B' : '#EF4444');
                        ?>
                        <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $row['id'] ?>"
                           class="d-block text-decoration-none mb-1 p-3 rounded-3"
                           style="background:<?= $isActive?'#1A1A1A':'#F9FAFB' ?>;border:1.5px solid <?= $isActive?'#F5A623':'#E5E7EB' ?>;transition:.15s;">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div style="font-size:12.5px;font-weight:<?= $isActive?'700':'500' ?>;color:<?= $isActive?'#fff':'#1A1A1A' ?>;line-height:1.35;flex:1;">
                                    <?= htmlspecialchars(mb_strimwidth($row['nama_indikator'],0,80,'…')) ?>
                                </div>
                                <span title="<?= $isFilled?'Lengkap':($isPartial?'Sebagian':'Belum diisi') ?>"
                                      style="width:8px;height:8px;border-radius:50%;background:<?= $dotColor ?>;flex-shrink:0;margin-top:5px;"></span>
                            </div>
                            <div class="d-flex gap-1 mt-1">
                                <span class="badge-<?= $lvl ?>" style="font-size:10px;"><?= $row['leveling'] ?></span>
                                <span class="badge-pic" style="font-size:10px;"><?= $row['pic'] ?></span>
                            </div>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Kanan: Form -->
            <div class="col-lg-8">
                <?php if ($specificId && $indikator): ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-3" style="border-radius:8px;font-size:13px;">
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Perhatian:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="card-box">
                    <!-- Info Indikator -->
                    <div class="card-box-header">
                        <div>
                            <div class="d-flex gap-2 mb-1">
                                <span class="badge-<?= str_replace('-','',strtolower($indikator['leveling'])) ?>"><?= $indikator['leveling'] ?></span>
                                <span class="badge-pic"><?= $indikator['pic'] ?></span>
                            </div>
                            <div style="font-size:13.5px;font-weight:700;color:#1A1A1A;line-height:1.4;max-width:600px;">
                                <?= htmlspecialchars($indikator['nama_indikator']) ?>
                            </div>
                            <div style="font-size:12px;color:#6B7280;margin-top:3px;">
                                Satuan: <strong><?= htmlspecialchars($indikator['satuan']) ?></strong>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="formRealisasi">
                        <input type="hidden" name="indikator_id" value="<?= $indikator['id'] ?>">

                        <div class="p-4">

                        <!-- Tab TW -->
                        <?php
                        // Tentukan tab aktif:
                        // 1. edit_tw dari URL (user klik Edit)
                        // 2. open_tw dari URL (setelah redirect simpan)
                        // 3. TW pertama yang belum terisi
                        // 4. Default TW 1
                        $activeTab = (int)($_GET['edit_tw'] ?? 0);
                        if (!$activeTab) $activeTab = (int)($_GET['open_tw'] ?? 0);
                        if (!$activeTab) {
                            $activeTab = 1;
                            foreach([1,2,3,4] as $twCheck) {
                                $chkVal = $realisasi["tw$twCheck"] ?? null;
                                if ($chkVal === null || $chkVal === '') {
                                    $activeTab = $twCheck;
                                    break;
                                }
                            }
                        }
                        ?>
                        <ul class="nav nav-tabs mb-0" id="twTabs" role="tablist" style="border-bottom:2px solid #F5A623;">
                            <?php foreach([1,2,3,4] as $tw):
                                $isOpen    = isTwOpen($tahun, $tw);
                                $twVal     = $realisasi["tw$tw"] ?? null;
                                $twTerisi  = ($twVal !== null && $twVal !== '');
                                // Status label di tab
                                if ($twTerisi) {
                                    $tabStatusColor = '#059669';
                                    $tabStatusText  = 'Terisi';
                                } elseif ($isOpen) {
                                    $tabStatusColor = '#F59E0B';
                                    $tabStatusText  = 'Buka';
                                } else {
                                    $tabStatusColor = '#EF4444';
                                    $tabStatusText  = 'Tutup';
                                }
                            ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $tw===$activeTab?'active':'' ?>" id="tab-tw<?= $tw ?>"
                                        data-bs-toggle="tab" data-bs-target="#panel-tw<?= $tw ?>"
                                        type="button" role="tab"
                                        style="font-weight:700;font-size:13px;<?= $tw===$activeTab?'color:#1A1A1A;background:#F5A623;border-color:#F5A623 #F5A623 #fff;':'color:#6B7280;' ?>">
                                    TW <?= $tw ?>
                                    <span style="font-size:10px;font-weight:600;margin-left:4px;color:<?= $tabStatusColor ?>;">
                                        (<?= $tabStatusText ?>)
                                    </span>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tab-content pt-4" id="twTabContent">
                            <?php
                            // Cek TW mana yang sedang dalam mode edit (via ?edit_tw=1)
                            $editTw = (int)($_GET['edit_tw'] ?? 0);

                            foreach([1,2,3,4] as $tw):
                                $isOpen   = isTwOpen($tahun, $tw);
                                $val      = $realisasi["tw$tw"]               ?? null;
                                $link     = $realisasi["link_tw$tw"]          ?? '';
                                $eval     = $realisasi["evaluasi_tw$tw"]      ?? '';
                                $rtl      = $realisasi["tindak_lanjut_tw$tw"] ?? '';
                                $twTerisi = ($val !== null && $val !== '');
                                $isEditMode = ($editTw === $tw); // user klik tombol Edit di TW ini
                            ?>
                            <div class="tab-pane fade <?= $tw===$activeTab?'show active':'' ?>" id="panel-tw<?= $tw ?>" role="tabpanel">

                                <?php if ($twTerisi && !$isEditMode): ?>
                                <!-- ===== MODE READ-ONLY: TW sudah terisi ===== -->
                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span style="background:#ECFDF5;color:#059669;border:1.5px solid #6EE7B7;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:700;">
                                        <i class="bi bi-check-circle-fill me-1"></i>Sudah Terisi
                                    </span>
                                    <?php if (!$isOpen): ?>
                                    <span style="background:#FEF3C7;color:#92400E;border:1.5px solid #FCD34D;border-radius:20px;padding:4px 12px;font-size:12px;font-weight:600;">
                                        <i class="bi bi-lock-fill me-1"></i>Periode Terkunci
                                    </span>
                                    <?php endif; ?>
                                </div>

                                <div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:10px;padding:16px 20px;" class="mb-3">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Realisasi TW <?= $tw ?></div>
                                            <div style="font-size:18px;font-weight:800;color:#1A1A1A;margin-top:2px;">
                                                <?= htmlspecialchars(number_format((float)$val, 2, ',', '.')) ?>
                                                <span style="font-size:13px;font-weight:600;color:#6B7280;"><?= htmlspecialchars($indikator['satuan']) ?></span>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Link Data Dukung</div>
                                            <div style="font-size:13px;margin-top:2px;">
                                                <?php if ($link): ?>
                                                <a href="<?= htmlspecialchars($link) ?>" target="_blank" style="color:#F5A623;word-break:break-all;">
                                                    <i class="bi bi-link-45deg me-1"></i><?= htmlspecialchars($link) ?>
                                                </a>
                                                <?php else: ?>
                                                <span style="color:#9CA3AF;">—</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Evaluasi / Penjelasan Capaian</div>
                                            <div style="font-size:13px;color:#374151;margin-top:2px;line-height:1.5;"><?= nl2br(htmlspecialchars($eval ?: '—')) ?></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div style="font-size:11px;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Rencana Tindak Lanjut</div>
                                            <div style="font-size:13px;color:#374151;margin-top:2px;line-height:1.5;"><?= nl2br(htmlspecialchars($rtl ?: '—')) ?></div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($isOpen): ?>
                                <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $specificId ?>&edit_tw=<?= $tw ?>#panel-tw<?= $tw ?>"
                                   class="btn-migas-outline btn" style="font-size:12.5px;">
                                    <i class="bi bi-pencil me-1"></i>Edit TW <?= $tw ?>
                                </a>
                                <?php else: ?>
                                <div style="font-size:12px;color:#9CA3AF;margin-top:4px;">
                                    <i class="bi bi-lock-fill me-1"></i>Hubungi admin untuk membuka periode TW <?= $tw ?> jika ingin mengedit.
                                </div>
                                <?php endif; ?>

                                <?php else: ?>
                                <!-- ===== MODE INPUT/EDIT FORM ===== -->

                                <?php if (!$isOpen && !$twTerisi): ?>
                                <div class="alert alert-warning" style="font-size:12.5px;border-radius:8px;">
                                    <i class="bi bi-lock-fill me-1"></i>
                                    Periode TW <?= $tw ?> belum dibuka oleh admin.
                                </div>
                                <?php endif; ?>

                                <?php if ($isEditMode): ?>
                                <div class="alert alert-info" style="font-size:12.5px;border-radius:8px;background:#EFF6FF;border-color:#BFDBFE;color:#1E40AF;">
                                    <i class="bi bi-pencil-square me-1"></i>
                                    Mode edit TW <?= $tw ?> —
                                    <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $specificId ?>" style="color:#1E40AF;font-weight:600;">Batal Edit</a>
                                </div>
                                <?php endif; ?>

                                <?php $dis = (!$isOpen && !$isEditMode) ? 'disabled' : ''; ?>

                                <!-- Realisasi + Satuan -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Realisasi TW <?= $tw ?> <span style="color:#EF4444;">*</span>
                                    </label>
                                    <div class="input-group" style="max-width:320px;">
                                        <input type="text" inputmode="decimal" name="tw<?= $tw ?>"
                                               class="form-control tw-input"
                                               value="<?= htmlspecialchars(str_replace('.', ',', $val)) ?>"
                                               placeholder="Contoh: 12,50"
                                               pattern="^\d+([,]\d{1,2})?$"
                                               title="Gunakan koma untuk desimal, maksimal 2 angka di belakang koma"
                                               autocomplete="off"
                                               <?= $dis ?>>
                                        <span class="input-group-text"
                                              style="background:#F5A623;border-color:#F5A623;color:#1A1A1A;font-weight:700;font-size:12.5px;">
                                            <?= htmlspecialchars($indikator['satuan']) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Link Data Dukung -->
                                <div class="mb-3">
                                    <label class="form-label">Link Data Dukung TW <?= $tw ?></label>
                                    <input type="url" name="link_tw<?= $tw ?>"
                                           class="form-control"
                                           value="<?= htmlspecialchars($link) ?>"
                                           placeholder="https://drive.google.com/..."
                                           <?= $dis ?>>
                                    <div class="form-text">Link ke dokumen atau bukti pendukung TW <?= $tw ?></div>
                                </div>

                                <!-- Evaluasi -->
                                <div class="mb-3">
                                    <label class="form-label">Evaluasi / Penjelasan Capaian TW <?= $tw ?></label>
                                    <textarea name="evaluasi_tw<?= $tw ?>" class="form-control" rows="3"
                                              placeholder="Tuliskan penjelasan capaian TW <?= $tw ?>..."
                                              <?= $dis ?>><?= htmlspecialchars($eval) ?></textarea>
                                </div>

                                <!-- Tindak Lanjut -->
                                <div class="mb-3">
                                    <label class="form-label">Rencana Tindak Lanjut TW <?= $tw ?></label>
                                    <textarea name="tindak_lanjut_tw<?= $tw ?>" class="form-control" rows="3"
                                              placeholder="Tuliskan rencana tindak lanjut TW <?= $tw ?>..."
                                              <?= $dis ?>><?= htmlspecialchars($rtl) ?></textarea>
                                </div>

                                <!-- Tombol Simpan per TW — hanya di mode input/edit -->
                                <div class="mt-4 d-flex gap-2">
                                    <button type="submit" class="btn-migas btn">
                                        <i class="bi bi-check2-circle me-1"></i>Simpan Realisasi
                                    </button>
                                    <a href="<?= BASE_URL ?>/user/realisasi.php?id=<?= $specificId ?>" class="btn-migas-outline btn">
                                        Batal
                                    </a>
                                </div>

                                <?php endif; // end read-only vs form ?>

                            </div>
                            <?php endforeach; ?>
                        </div>

                        </div><!-- /p-4 -->
                    </form>
                </div>

                <?php else: ?>
                <div class="card-box p-5 text-center" style="border:2px dashed #E5E7EB;">
                    <i class="bi bi-arrow-left-circle" style="font-size:40px;color:#D1D5DB;"></i>
                    <div style="font-size:14px;color:#9CA3AF;margin-top:12px;font-weight:500;">
                        Pilih indikator di sebelah kiri untuk mulai mengisi realisasi
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('#twTabs button').forEach(btn => {
    btn.addEventListener('shown.bs.tab', function() {
        document.querySelectorAll('#twTabs button').forEach(b => {
            b.style.color = '#6B7280';
            b.style.background = '';
            b.style.borderColor = '';
        });
        this.style.color = '#1A1A1A';
        this.style.background = '#F5A623';
        this.style.borderColor = '#F5A623 #F5A623 #fff';
    });
});
</script>

<script>
// Validasi & format input TW: hanya angka dan koma, max 2 desimal
document.querySelectorAll('.tw-input').forEach(function(input) {
    input.addEventListener('input', function() {
        // Hapus karakter selain angka dan koma
        this.value = this.value.replace(/[^0-9,]/g, '');
        // Pastikan hanya ada 1 koma
        var parts = this.value.split(',');
        if (parts.length > 2) {
            this.value = parts[0] + ',' + parts.slice(1).join('');
        }
        // Max 2 digit setelah koma
        if (parts[1] && parts[1].length > 2) {
            this.value = parts[0] + ',' + parts[1].substring(0, 2);
        }
    });
    // Validasi saat form submit
    input.closest('form')?.addEventListener('submit', function(e) {
        var val = input.value.trim();
        if (val && !/^\d+([,]\d{1,2})?$/.test(val)) {
            e.preventDefault();
            input.focus();
            input.setCustomValidity('Format tidak valid. Contoh: 12,50');
            input.reportValidity();
        } else {
            input.setCustomValidity('');
        }
    });
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>