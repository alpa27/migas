<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$db    = getDB();
$tahun = getAktifTahun();
$tid   = $tahun['id'] ?? 0;
$id    = (int)($_GET['id'] ?? 0);
$pageTitle = 'Edit Realisasi';

$stmt = $db->prepare("SELECT * FROM indikator WHERE id = ? AND tahun_id = ?");
$stmt->bind_param('ii', $id, $tid);
$stmt->execute();
$indikator = $stmt->get_result()->fetch_assoc();
if (!$indikator) redirect(BASE_URL . '/admin/indikator.php');

$stmt2 = $db->prepare("SELECT * FROM realisasi WHERE indikator_id = ? AND tahun_id = ?");
$stmt2->bind_param('ii', $id, $tid);
$stmt2->execute();
$realisasi = $stmt2->get_result()->fetch_assoc() ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [];
    foreach ([1,2,3,4] as $tw) {
        $d["tw$tw"]               = $_POST["tw$tw"] !== '' ? (float)$_POST["tw$tw"] : null;
        $d["link_tw$tw"]          = trim($_POST["link_tw$tw"] ?? '');
        $d["evaluasi_tw$tw"]      = trim($_POST["evaluasi_tw$tw"] ?? '');
        $d["tindak_lanjut_tw$tw"] = trim($_POST["tindak_lanjut_tw$tw"] ?? '');
    }
    $d['total'] = $_POST['total_realisasi'] !== '' ? (float)$_POST['total_realisasi'] : null;
    $uid = $_SESSION['user_id'];

    if ($realisasi) {
        $upd = $db->prepare("UPDATE realisasi SET
            tw1=?,tw2=?,tw3=?,tw4=?,total_realisasi=?,
            link_tw1=?,evaluasi_tw1=?,tindak_lanjut_tw1=?,
            link_tw2=?,evaluasi_tw2=?,tindak_lanjut_tw2=?,
            link_tw3=?,evaluasi_tw3=?,tindak_lanjut_tw3=?,
            link_tw4=?,evaluasi_tw4=?,tindak_lanjut_tw4=?,
            updated_by=? WHERE indikator_id=? AND tahun_id=?");
        $upd->bind_param('dddddssssssssssssiii',
            $d['tw1'],$d['tw2'],$d['tw3'],$d['tw4'],$d['total'],
            $d['link_tw1'],$d['evaluasi_tw1'],$d['tindak_lanjut_tw1'],
            $d['link_tw2'],$d['evaluasi_tw2'],$d['tindak_lanjut_tw2'],
            $d['link_tw3'],$d['evaluasi_tw3'],$d['tindak_lanjut_tw3'],
            $d['link_tw4'],$d['evaluasi_tw4'],$d['tindak_lanjut_tw4'],
            $uid, $id, $tid
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
            $id,$tid,
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
    redirect(BASE_URL . '/admin/indikator.php');
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-wrapper">
    <div class="topbar">
        <span class="topbar-title"><i class="bi bi-pencil me-2"></i>Edit Realisasi — <?= htmlspecialchars(mb_strimwidth($indikator['nama_indikator'],0,60,'…')) ?></span>
        <a href="<?= BASE_URL ?>/admin/indikator.php" class="btn-migas-outline btn" style="font-size:12.5px;padding:6px 14px;">
            <i class="bi bi-arrow-left me-1"></i>Kembali
        </a>
    </div>

    <div class="page-content">
        <div class="card-box">
            <div class="card-box-header">
                <div>
                    <div class="d-flex gap-2 mb-1">
                        <span class="badge-<?= str_replace('-','',strtolower($indikator['leveling'])) ?>"><?= $indikator['leveling'] ?></span>
                        <span class="badge-pic"><?= $indikator['pic'] ?></span>
                    </div>
                    <div style="font-size:14px;font-weight:700;color:#1A1A1A;line-height:1.4;">
                        <?= htmlspecialchars($indikator['nama_indikator']) ?>
                    </div>
                    <div style="font-size:12px;color:#6B7280;margin-top:3px;">Satuan: <strong><?= htmlspecialchars($indikator['satuan']) ?></strong></div>
                </div>
            </div>

            <form method="POST" class="p-4">

                <!-- Tab TW -->
                <ul class="nav nav-tabs mb-0" id="twTabs" style="border-bottom:2px solid #F5A623;">
                    <?php foreach([1,2,3,4] as $tw): ?>
                    <li class="nav-item">
                        <button class="nav-link <?= $tw===1?'active':'' ?>" id="tab-tw<?= $tw ?>"
                                data-bs-toggle="tab" data-bs-target="#panel-tw<?= $tw ?>"
                                type="button"
                                style="font-weight:700;font-size:13px;<?= $tw===1?'color:#1A1A1A;background:#F5A623;border-color:#F5A623 #F5A623 #fff;':'color:#6B7280;' ?>">
                            TW <?= $tw ?>
                        </button>
                    </li>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content pt-4" id="twTabContent">
                    <?php foreach([1,2,3,4] as $tw):
                        $val  = $realisasi["tw$tw"] ?? '';
                        $link = $realisasi["link_tw$tw"] ?? '';
                        $eval = $realisasi["evaluasi_tw$tw"] ?? '';
                        $rtl  = $realisasi["tindak_lanjut_tw$tw"] ?? '';
                    ?>
                    <div class="tab-pane fade <?= $tw===1?'show active':'' ?>" id="panel-tw<?= $tw ?>">
                        <div class="mb-3">
                            <label class="form-label">Realisasi TW <?= $tw ?></label>
                            <input type="number" step="0.0001" name="tw<?= $tw ?>"
                                   class="form-control" style="max-width:220px;"
                                   value="<?= htmlspecialchars($val) ?>" placeholder="—">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Link Data Dukung TW <?= $tw ?></label>
                            <input type="url" name="link_tw<?= $tw ?>" class="form-control"
                                   value="<?= htmlspecialchars($link) ?>" placeholder="https://...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Evaluasi TW <?= $tw ?></label>
                            <textarea name="evaluasi_tw<?= $tw ?>" class="form-control" rows="3"
                                      placeholder="Penjelasan capaian TW <?= $tw ?>..."><?= htmlspecialchars($eval) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Rencana Tindak Lanjut TW <?= $tw ?></label>
                            <textarea name="tindak_lanjut_tw<?= $tw ?>" class="form-control" rows="3"
                                      placeholder="Tindak lanjut TW <?= $tw ?>..."><?= htmlspecialchars($rtl) ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Total Manual -->
                <div class="mt-3 p-3 rounded-3" style="background:#F9FAFB;border:1.5px solid #E5E7EB;">
                    <label class="form-label">Total Realisasi (Isi Manual)</label>
                    <input type="number" step="0.0001" name="total_realisasi"
                           class="form-control" style="max-width:220px;"
                           value="<?= htmlspecialchars($realisasi['total_realisasi'] ?? '') ?>"
                           placeholder="Total keseluruhan">
                    <div class="form-text">Isi total realisasi secara manual.</div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn-migas btn">
                        <i class="bi bi-check2-circle me-1"></i>Simpan
                    </button>
                    <a href="<?= BASE_URL ?>/admin/indikator.php" class="btn-migas-outline btn">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>