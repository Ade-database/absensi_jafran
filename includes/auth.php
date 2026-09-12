<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Wajib login. Panggil di paling atas setiap halaman admin. */
function wajib_login(): void
{
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}

/** Data admin yang sedang login (dari session, bukan query ulang tiap saat). */
function admin_sekarang(): array
{
    return [
        'id'     => $_SESSION['admin_id'] ?? null,
        'nama'   => $_SESSION['admin_nama'] ?? 'Admin',
        'username' => $_SESSION['admin_username'] ?? '',
    ];
}