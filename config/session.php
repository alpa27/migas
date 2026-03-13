<?php
// config/session.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', 'http://localhost/migas-kinerja');
define('LOGO_URL', 'https://migas.esdm.go.id/cms/assets/frontend/img/faviconnew.png');

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if ($_SESSION['role'] !== 'admin') {
        header('Location: ' . BASE_URL . '/user/dashboard.php');
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'id'             => $_SESSION['user_id'] ?? null,
        'username'       => $_SESSION['username'] ?? '',
        'nama'           => $_SESSION['nama'] ?? '',
        'role'           => $_SESSION['role'] ?? '',
        'kode_kelompok'  => $_SESSION['kode_kelompok'] ?? '',
    ];
}

/**
 * Bangun kondisi WHERE untuk filter PIC berdasarkan kode kelompok user.
 * Jika 3 huruf => LIKE 'XXX%', jika 4+ huruf => = 'XXXX'
 */
function buildPicCondition(string $kode, string $alias = ''): array {
    $col = $alias ? "$alias.pic" : "pic";
    if (strlen($kode) >= 4) {
        return ["$col = ?", $kode];
    }
    return ["$col LIKE ?", $kode . '%'];
}

function getAktifTahun(): ?array {
    $db = getDB();
    $r = $db->query("SELECT * FROM tahun_anggaran WHERE is_aktif = 1 LIMIT 1");
    return $r->num_rows ? $r->fetch_assoc() : null;
}

function isTwOpen(array $tahun, int $tw): bool {
    return (bool)($tahun["tw{$tw}_open"] ?? false);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function flash(string $key, string $msg): void {
    $_SESSION["flash_$key"] = $msg;
}

function getFlash(string $key): string {
    $msg = $_SESSION["flash_$key"] ?? '';
    unset($_SESSION["flash_$key"]);
    return $msg;
}

/**
 * Catat aktivitas ke tabel activity_log
 *
 * @param string $action     Kode aksi singkat, misal: 'LOGIN', 'SIMPAN_REALISASI'
 * @param string $description Keterangan lengkap aktivitas
 * @param string $module     Modul terkait, misal: 'realisasi', 'indikator', 'users'
 */
function logActivity(string $action, string $description = '', string $module = ''): void {
    try {
        $db       = getDB();
        $userId   = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'guest';
        $role     = $_SESSION['role'] ?? null;
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? '0.0.0.0';

        $stmt = $db->prepare("
            INSERT INTO activity_log (user_id, username, role, action, description, module, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issssss', $userId, $username, $role, $action, $description, $module, $ip);
        $stmt->execute();
    } catch (Throwable $e) {
        // Jangan sampai error log mengganggu jalannya aplikasi
    }
}