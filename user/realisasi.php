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

// Verify ownership
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
    $iid = (int)$_POST['indikator_id'];

    // Verify ownership
    [$condition, $param] = buildPicCondition($kode, 'i');
    $sv = $db->prepare("SELECT id FROM indikator i WHERE i.id=? AND i.tahun_id=? AND $condition");
    $sv->bind_param('iss', $iid, $tid, $param);
    $sv->execute();
    if (!$sv->get_result()->num_rows) redirect(BASE_URL . '/user/indikator.php');

    // Validasi: semua TW yang terbuka wajib diisi beserta link, evaluasi, tindak lanjut
    $errors = [];
    foreach ([1,2,3,4] as $tw) {
        if (!isTwOpen($tahun, $tw)) continue;
        $val = trim($_POST["tw$tw"] ?? '');
        $link = trim($_POST["link_tw$tw"] ?? '');
        $eval = trim($_POST["evaluasi_tw$tw"] ?? '');
        $rtl  = trim($_POST["tindak_lanjut_tw$tw"] ?? '');
        if ($val === '') $errors[] = "Realisasi TW $tw wajib diisi.";
        if ($link === '') $errors[] = "Link Data Dukung TW $tw wajib diisi.";
        if ($eval === '') $errors[] = "Evaluasi TW $tw wajib diisi.";
        if ($rtl  === '') $errors[] = "Tindak Lanjut TW $tw wajib diisi.";
    }

    if (empty($errors)) {
        $d = [];
        foreach ([1,2,3,4] as $tw) {
            $d["tw$tw"]               = isTwOpen($tahun, $tw) && $_POST["tw$tw"] !== '' ? (float)$_POST["tw$tw"] : null;
            $d["link_tw$tw"]          = isTwOpen($tahun, $tw) ? trim($_POST["link_tw$tw"] ?? '') : null;
            $d["evaluasi_tw$tw"]      = isTwOpen($tahun, $tw) ? trim($_POST["evaluasi_tw$tw"] ?? '') : null;
            $d["tindak_lanjut_tw$tw"] = isTwOpen($tahun, $tw) ? trim($_POST["tindak_lanjut_tw$tw"] ?? '') : null;
        }
        $d['total'] = $_POST['total_realisasi'] !== '' ? (float)$_POST['total_realisasi'] : null;
        $uid = $user['id'];

        $check = $db->prepare("SELECT id FROM realisasi WHERE indikator_id=? AND tahun_id=?");
        $check->bind_param('ii', $iid, $tid);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;

        if ($exists) {
            $upd = $db->prepare("UPDATE realisasi SET
                tw1=?,tw2=?,tw3=?,tw4=?,total_realisasi=?,
                link_tw1=?,evaluasi_tw1=?,tindak_lanjut_tw1=?,
                link_tw2=?,evaluasi_tw2=?,tindak_lanjut_tw2=?,
                link_tw3=?,evaluasi_tw3=?,tindak_lanjut_tw3=?,
                link_tw4=?,evaluasi_tw4=?,tindak_lanjut_tw4=?,
                updated_by=?
                WHERE indikator_id=? AND tahun_id=?");
            $upd->bind_param('dddddssssssssssssiii',
                $d['tw1'],$d['tw2'],$d['tw3'],$d['tw4'],$d['total'],
                $d['link_tw1'],$d['evaluasi_tw1'],$d['tindak_lanjut_tw1'],
                $d['link_tw2'],$d['evaluasi_tw2'],$d['tindak_lanjut_tw2'],
                $d['link_tw3'],$d['evaluasi_tw3'],$d['tindak_lanjut_tw3'],
                $d['link_tw4'],$d['evaluasi_tw4'],$d['tindak_lanjut_tw4'],
                $uid, $iid, $tid
            );
            $upd->execute();
        } else {
            $ins = $db->prepare("INSERT INTO realisasi
                (indikator_id,tahun_id,tw1,tw2,tw3,tw4,total_realisasi,
                link_tw1,evaluasi_tw1,tindak_lanjut_tw1,
                link_tw2,evaluasi_tw2,tindak_lanjut_tw2,
                link_tw3,evaluasi_tw3,tindak_lanjut_tw3,
                link_tw4,evaluasi_tw4,tindak_lanjut_tw4,updated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $ins->bind_param('iidddddsssssssssssi',
                $iid,$tid,
                $d['tw1'],$d['tw2'],$d['tw3'],$d['tw4'],$d['total'],
                $d['link_tw1'],$d['evaluasi_tw1'],$d['tindak_lanjut_tw1'],
                $d['link_tw2'],$d['evaluasi_tw2'],$d['tindak_lanjut_tw2'],
                $d['link_tw3'],$d['evaluasi_tw3'],$d['tindak_lanjut_tw3'],
                $d['link_tw4'],$d['evaluasi_tw4'],$d['tindak_lanjut_tw4'],
                $uid
            );
            $ins->execute();
        }

        flash('success', 'Realisasi berhasil disimpan.');
        redirect(BASE_URL . '/user/realisasi.php?id=' . $iid);
    }
}

// Load all indikator list
[$condition, $param] = buildPicCondition($kode, 'i');
$stmt = $db->prepare("
    SELECT i.*, r.tw1,r.tw2,r.tw3,r.tw4,r.total_realisasi,r.id AS real_id
    FROM indikator i
    LEFT JOIN realisasi r ON r.indikator_id=i.id AND r.tahun_id=i.tahun_id
    WHERE i.tahun_id=$tid AND $condition
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
            <div class="alert alert-success alert-auto-hide"><?= $msg ?></div>
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
                            $isFilled = $row['tw1']!==null && $row['tw2']!==null && $row['tw3']!==null && $row['tw4']!==null;
                            $isPartial = !$isFilled && ($row['tw1']!==null || $row['tw2']!==null || $row['tw3']!==null || $row['tw4']!==null);
                            $isActive  = $specificId == $row['id'];
                            $lvl = str_replace('-','',strtolower($row['leveling']));
                            $dotColor = $isFilled ? '#059669' : ($isPartial ? '#F59E0B' : '#EF4444');
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
                <?php if ($specificId && isset($indikator)): ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-3" style="border-radius:8px;font-size:13px;">
                    <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Harap lengkapi semua kolom berikut:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach($errors as $e): ?>
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
                        <ul class="nav nav-tabs mb-0" id="twTabs" role="tablist" style="border-bottom:2px solid #F5A623;">
                            <?php foreach([1,2,3,4] as $tw):
                                $isOpen = isTwOpen($tahun, $tw);
                            ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?= $tw===1?'active':'' ?>" id="tab-tw<?= $tw ?>"
                                        data-bs-toggle="tab" data-bs-target="#panel-tw<?= $tw ?>"
                                        type="button" role="tab"
                                        style="font-weight:700;font-size:13px;<?= $tw===1?'color:#1A1A1A;background:#F5A623;border-color:#F5A623 #F5A623 #fff;':'color:#6B7280;' ?>">
                                    TW <?= $tw ?>
                                    <span style="font-size:10px;font-weight:600;margin-left:4px;color:<?= $isOpen?'#059669':'#EF4444' ?>;">
                                        (<?= $isOpen?'Buka':'Tutup' ?>)
                                    </span>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="tab-content pt-4" id="twTabContent">
                            <?php foreach([1,2,3,4] as $tw):
                                $isOpen = isTwOpen($tahun, $tw);
                                $val   = $realisasi["tw$tw"] ?? '';
                                $link  = $realisasi["link_tw$tw"] ?? '';
                                $eval  = $realisasi["evaluasi_tw$tw"] ?? '';
                                $rtl   = $realisasi["tindak_lanjut_tw$tw"] ?? '';
                                $dis   = !$isOpen ? 'disabled' : '';
                            ?>
                            <div class="tab-pane fade <?= $tw===1?'show active':'' ?>" id="panel-tw<?= $tw ?>" role="tabpanel">

                                <?php if (!$isOpen): ?>
                                <div class="alert alert-warning" style="font-size:12.5px;border-radius:8px;">
                                    <i class="bi bi-lock-fill me-1"></i>
                                    Periode TW <?= $tw ?> belum dibuka oleh admin. Data tidak dapat diubah.
                                </div>
                                <?php endif; ?>

                                <!-- Realisasi -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Realisasi TW <?= $tw ?>
                                        <?= $isOpen ? '<span style="color:#EF4444;">*</span>' : '' ?>
                                    </label>
                                    <input type="number" step="0.0001" name="tw<?= $tw ?>"
                                           class="form-control" value="<?= htmlspecialchars($val) ?>"
                                           placeholder="Masukkan nilai realisasi"
                                           <?= $dis ?> <?= $isOpen ? 'required' : '' ?>>
                                    <?php if (!$isOpen): ?>
                                    <input type="hidden" name="tw<?= $tw ?>" value="<?= htmlspecialchars($val) ?>">
                                    <?php endif; ?>
                                </div>

                                <!-- Link Data Dukung -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Link Data Dukung TW <?= $tw ?>
                                        <?= $isOpen ? '<span style="color:#EF4444;">*</span>' : '' ?>
                                    </label>
                                    <input type="url" name="link_tw<?= $tw ?>"
                                           class="form-control" value="<?= htmlspecialchars($link) ?>"
                                           placeholder="https://drive.google.com/..."
                                           <?= $dis ?>>
                                    <?php if (!$isOpen): ?>
                                    <input type="hidden" name="link_tw<?= $tw ?>" value="<?= htmlspecialchars($link) ?>">
                                    <?php endif; ?>
                                    <div class="form-text">Link ke dokumen atau bukti pendukung TW <?= $tw ?></div>
                                </div>

                                <!-- Evaluasi -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Evaluasi / Penjelasan Capaian TW <?= $tw ?>
                                        <?= $isOpen ? '<span style="color:#EF4444;">*</span>' : '' ?>
                                    </label>
                                    <textarea name="evaluasi_tw<?= $tw ?>" class="form-control" rows="3"
                                              placeholder="Tuliskan penjelasan capaian TW <?= $tw ?>..."
                                              <?= $dis ?>><?= htmlspecialchars($eval) ?></textarea>
                                    <?php if (!$isOpen): ?>
                                    <input type="hidden" name="evaluasi_tw<?= $tw ?>" value="<?= htmlspecialchars($eval) ?>">
                                    <?php endif; ?>
                                </div>

                                <!-- Tindak Lanjut -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        Rencana Tindak Lanjut TW <?= $tw ?>
                                        <?= $isOpen ? '<span style="color:#EF4444;">*</span>' : '' ?>
                                    </label>
                                    <textarea name="tindak_lanjut_tw<?= $tw ?>" class="form-control" rows="3"
                                              placeholder="Tuliskan rencana tindak lanjut TW <?= $tw ?>..."
                                              <?= $dis ?>><?= htmlspecialchars($rtl) ?></textarea>
                                    <?php if (!$isOpen): ?>
                                    <input type="hidden" name="tindak_lanjut_tw<?= $tw ?>" value="<?= htmlspecialchars($rtl) ?>">
                                    <?php endif; ?>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Total Realisasi Manual -->
                        <div class="mt-3 p-3 rounded-3" style="background:#F9FAFB;border:1.5px solid #E5E7EB;">
                            <label class="form-label">Total Realisasi (Isi Manual)</label>
                            <input type="number" step="0.0001" name="total_realisasi"
                                   class="form-control" style="max-width:220px;"
                                   value="<?= htmlspecialchars($realisasi['total_realisasi'] ?? '') ?>"
                                   placeholder="Total keseluruhan">
                            <div class="form-text">Isi total realisasi TW I + II + III + IV secara manual.</div>
                        </div>

                        <div class="mt-4 d-flex gap-2">
                            <button type="submit" class="btn-migas btn">
                                <i class="bi bi-check2-circle me-1"></i>Simpan Realisasi
                            </button>
                            <a href="<?= BASE_URL ?>/user/indikator.php" class="btn-migas-outline btn">
                                Batal
                            </a>
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
// Aktifkan tab styling on click
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

<?php include __DIR__ . '/../includes/footer.php'; ?>