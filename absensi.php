<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';

wajib_login();

/*
|--------------------------------------------------------------------------
| PROSES POST
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | TAMBAH / EDIT ABSENSI MANUAL
    |--------------------------------------------------------------------------
    */

    if ($aksi === 'tambah' || $aksi === 'edit') {

        $employee_id = (int) ($_POST['employee_id'] ?? 0);
        $tanggal = $_POST['tanggal'] ?? '';
        $jam_masuk = !empty($_POST['jam_masuk_aktual'])
            ? $_POST['jam_masuk_aktual']
            : null;
        $jam_pulang = !empty($_POST['jam_pulang_aktual'])
            ? $_POST['jam_pulang_aktual']
            : null;

        $catatan = trim($_POST['catatan'] ?? '');

        if (!$employee_id || $tanggal === '') {

            flash(
                'error',
                'Karyawan dan tanggal wajib diisi.'
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | Ambil data karyawan + shift
            |--------------------------------------------------------------------------
            */

            $emp = $pdo->prepare(
                'SELECT
                    e.*,
                    s.jam_masuk AS shift_masuk,
                    s.toleransi_menit,
                    s.is_lembur
                 FROM employees e
                 LEFT JOIN shifts s ON s.id = e.shift_id
                 WHERE e.id = ?'
            );

            $emp->execute([$employee_id]);

            $emp = $emp->fetch();

            if (!$emp) {

                flash(
                    'error',
                    'Karyawan tidak ditemukan.'
                );

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hitung status berdasarkan shift
                |--------------------------------------------------------------------------
                */

                if ($emp['shift_masuk']) {

                    [$status, $menit_telat] = hitung_status(
                        $jam_masuk,
                        $emp['shift_masuk'],
                        (int) $emp['toleransi_menit']
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Kalau shift lembur dan status masih hadir,
                    | ubah menjadi lembur.
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $status === 'hadir' &&
                        !empty($emp['is_lembur'])
                    ) {
                        $status = 'lembur';
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Kalau belum punya shift
                    |--------------------------------------------------------------------------
                    */

                    $status = $jam_masuk
                        ? 'hadir'
                        : 'alpha';

                    $menit_telat = 0;
                }

                /*
                |--------------------------------------------------------------------------
                | Simpan ke database
                |--------------------------------------------------------------------------
                */

                try {

                    $pdo->prepare(
                        "INSERT INTO attendance_daily
                        (
                            employee_id,
                            tanggal,
                            jam_masuk_aktual,
                            jam_pulang_aktual,
                            status,
                            menit_telat,
                            catatan,
                            perlu_tinjau,
                            sumber
                        )
                        VALUES (?,?,?,?,?,?,?,0,'manual')

                        ON DUPLICATE KEY UPDATE

                            jam_masuk_aktual = VALUES(jam_masuk_aktual),
                            jam_pulang_aktual = VALUES(jam_pulang_aktual),
                            status = VALUES(status),
                            menit_telat = VALUES(menit_telat),
                            catatan = VALUES(catatan),
                            perlu_tinjau = 0,
                            sumber = 'manual'"
                    )->execute([
                        $employee_id,
                        $tanggal,
                        $jam_masuk,
                        $jam_pulang,
                        $status,
                        $menit_telat,
                        $catatan
                    ]);

                    flash(
                        'success',
                        'Absensi berhasil disimpan.'
                    );

                } catch (PDOException $e) {

                    /*
                    |--------------------------------------------------------------------------
                    | Fallback SQLite / database tanpa ON DUPLICATE KEY
                    |--------------------------------------------------------------------------
                    */

                    $cek = $pdo->prepare(
                        'SELECT id
                         FROM attendance_daily
                         WHERE employee_id = ?
                         AND tanggal = ?'
                    );

                    $cek->execute([
                        $employee_id,
                        $tanggal
                    ]);

                    $ada = $cek->fetch();

                    if ($ada) {

                        $pdo->prepare(
                            "UPDATE attendance_daily
                             SET
                                jam_masuk_aktual = ?,
                                jam_pulang_aktual = ?,
                                status = ?,
                                menit_telat = ?,
                                catatan = ?,
                                perlu_tinjau = 0,
                                sumber = 'manual'
                             WHERE id = ?"
                        )->execute([
                            $jam_masuk,
                            $jam_pulang,
                            $status,
                            $menit_telat,
                            $catatan,
                            $ada['id']
                        ]);

                    } else {

                        $pdo->prepare(
                            "INSERT INTO attendance_daily
                            (
                                employee_id,
                                tanggal,
                                jam_masuk_aktual,
                                jam_pulang_aktual,
                                status,
                                menit_telat,
                                catatan,
                                perlu_tinjau,
                                sumber
                            )
                            VALUES (?,?,?,?,?,?,?,0,'manual')"
                        )->execute([
                            $employee_id,
                            $tanggal,
                            $jam_masuk,
                            $jam_pulang,
                            $status,
                            $menit_telat,
                            $catatan
                        ]);
                    }

                    flash(
                        'success',
                        'Absensi berhasil disimpan.'
                    );
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS SATU DATA
    |--------------------------------------------------------------------------
    */

    elseif ($aksi === 'hapus') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {

            $stmt = $pdo->prepare(
                'DELETE FROM attendance_daily WHERE id = ?'
            );

            $stmt->execute([$id]);

            flash(
                'success',
                'Data absensi berhasil dihapus.'
            );

        } else {

            flash(
                'error',
                'ID data absensi tidak valid.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS DATA YANG DIPILIH
    |--------------------------------------------------------------------------
    */

    elseif ($aksi === 'hapus_batch') {

        $ids = $_POST['ids'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Pastikan berupa array
        |--------------------------------------------------------------------------
        */

        if (!is_array($ids)) {
            $ids = [];
        }

        /*
        |--------------------------------------------------------------------------
        | Bersihkan ID
        |--------------------------------------------------------------------------
        */

        $ids = array_map('intval', $ids);

        /*
        |--------------------------------------------------------------------------
        | Buang ID 0 / invalid
        |--------------------------------------------------------------------------
        */

        $ids = array_values(
            array_filter(
                $ids,
                function ($id) {
                    return $id > 0;
                }
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Hilangkan ID duplikat
        |--------------------------------------------------------------------------
        */

        $ids = array_values(array_unique($ids));

        if (empty($ids)) {

            flash(
                'error',
                'Tidak ada data yang dipilih.'
            );

        } else {

            /*
            |--------------------------------------------------------------------------
            | Buat placeholder ?,?,?
            |--------------------------------------------------------------------------
            */

            $placeholders = implode(
                ',',
                array_fill(0, count($ids), '?')
            );

            /*
            |--------------------------------------------------------------------------
            | Hapus data yang dipilih
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare(
                "DELETE FROM attendance_daily
                 WHERE id IN ($placeholders)"
            );

            $stmt->execute($ids);

            $jumlah = $stmt->rowCount();

            flash(
                'success',
                $jumlah . ' data absensi berhasil dihapus.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HAPUS SEMUA DATA ABSENSI
    |--------------------------------------------------------------------------
    */

    elseif ($aksi === 'hapus_semua') {

        /*
        |--------------------------------------------------------------------------
        | Hapus seluruh data dari attendance_daily.
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            'DELETE FROM attendance_daily'
        );

        $stmt->execute();

        $jumlah = $stmt->rowCount();

        flash(
            'success',
            $jumlah . ' data absensi berhasil dihapus.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | KEMBALI KE HALAMAN ABSENSI
    |--------------------------------------------------------------------------
    */

    redirect(
        'absensi.php' .
        (
            !empty($_POST['redirect_qs'])
                ? '?' . $_POST['redirect_qs']
                : ''
        )
    );
}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

$tanggal_dari = $_GET['dari'] ?? date('Y-m-01');

$tanggal_sampai = $_GET['sampai'] ?? date('Y-m-d');

$departemen_id = $_GET['departemen_id'] ?? '';

$status_filter = $_GET['status'] ?? '';

$hanya_tinjau = !empty(
    $_GET['hanya_tinjau']
);


/*
|--------------------------------------------------------------------------
| QUERY DATA ABSENSI
|--------------------------------------------------------------------------
*/

$sql = '
    SELECT
        a.*,
        e.nama,
        e.nik,
        d.nama_departemen

    FROM attendance_daily a

    JOIN employees e
        ON e.id = a.employee_id

    LEFT JOIN departments d
        ON d.id = e.departemen_id

    WHERE a.tanggal BETWEEN ? AND ?
';

$params = [
    $tanggal_dari,
    $tanggal_sampai
];


/*
|--------------------------------------------------------------------------
| FILTER DEPARTEMEN
|--------------------------------------------------------------------------
*/

if ($departemen_id !== '') {

    $sql .= '
        AND e.departemen_id = ?
    ';

    $params[] = (int) $departemen_id;
}


/*
|--------------------------------------------------------------------------
| FILTER STATUS
|--------------------------------------------------------------------------
*/

if ($status_filter !== '') {

    $sql .= '
        AND a.status = ?
    ';

    $params[] = $status_filter;
}


/*
|--------------------------------------------------------------------------
| FILTER PERLU DITINJAU
|--------------------------------------------------------------------------
*/

if ($hanya_tinjau) {

    $sql .= '
        AND a.perlu_tinjau = 1
    ';
}


/*
|--------------------------------------------------------------------------
| URUTAN DATA
|--------------------------------------------------------------------------
*/

$sql .= '
    ORDER BY
        a.tanggal DESC,
        e.nama

    LIMIT 200
';


/*
|--------------------------------------------------------------------------
| EKSEKUSI QUERY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$logs = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA UNTUK FORM TAMBAH
|--------------------------------------------------------------------------
*/

$karyawan_list = $pdo->query(
    '
    SELECT
        id,
        nik,
        nama

    FROM employees

    WHERE status_aktif = 1

    ORDER BY nama
    '
)->fetchAll();


/*
|--------------------------------------------------------------------------
| DATA DEPARTEMEN
|--------------------------------------------------------------------------
*/

$departemen_list = $pdo->query(
    '
    SELECT *
    FROM departments
    ORDER BY nama_departemen
    '
)->fetchAll();


/*
|--------------------------------------------------------------------------
| QUERY STRING UNTUK MEMPERTAHANKAN FILTER
|--------------------------------------------------------------------------
*/

$qs = http_build_query([
    'dari' => $tanggal_dari,
    'sampai' => $tanggal_sampai,
    'departemen_id' => $departemen_id,
    'status' => $status_filter,
    'hanya_tinjau' => $hanya_tinjau ? 1 : ''
]);


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

$page_title = 'Log Absensi';

$nav_active = 'absensi';

include __DIR__ . '/includes/header.php';

?>

<header class="page__header">

    <div class="page__headline">

        <h1 class="page__title">
            Log Absensi
        </h1>

        <p class="page__description">
            Riwayat kehadiran karyawan. Data terisi otomatis dari mesin setelah integrasi aktif; sementara bisa ditambah manual.
        </p>

    </div>


    <div class="page__action">

        <button
            type="button"
            class="button button--primary"
            data-stisla-dialog-trigger="tambahAbsensi"
        >

            <?= ic('solar:add-circle-linear') ?>

            Tambah Manual

        </button>

    </div>

</header>


<div class="page__body">

    <?php include __DIR__ . '/includes/flash.php'; ?>


    <!-- ============================================================
         FILTER
    ============================================================= -->

    <section class="page__section">

        <div class="card">

            <div class="card__body">

                <form
                    method="get"
                    class="grid grid-cols-12 gap-4 items-end"
                >

                    <!-- DARI TANGGAL -->

                    <div class="col-span-12 sm:col-span-6 lg:col-span-3">

                        <div class="field">

                            <label class="field__label">
                                Dari Tanggal
                            </label>

                            <input
                                type="date"
                                name="dari"
                                class="input"
                                value="<?= e($tanggal_dari) ?>"
                            >

                        </div>

                    </div>


                    <!-- SAMPAI TANGGAL -->

                    <div class="col-span-12 sm:col-span-6 lg:col-span-3">

                        <div class="field">

                            <label class="field__label">
                                Sampai Tanggal
                            </label>

                            <input
                                type="date"
                                name="sampai"
                                class="input"
                                value="<?= e($tanggal_sampai) ?>"
                            >

                        </div>

                    </div>


                    <!-- DEPARTEMEN -->

                    <div class="col-span-12 sm:col-span-6 lg:col-span-3">

                        <div class="field">

                            <label class="field__label">
                                Departemen
                            </label>

                            <select
                                name="departemen_id"
                                class="select"
                            >

                                <option value="">
                                    Semua
                                </option>

                                <?php foreach ($departemen_list as $d): ?>

                                    <option
                                        value="<?= (int) $d['id'] ?>"
                                        <?= (string) $departemen_id === (string) $d['id'] ? 'selected' : '' ?>
                                    >

                                        <?= e($d['nama_departemen']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- STATUS -->

                    <div class="col-span-12 sm:col-span-6 lg:col-span-3">

                        <div class="field">

                            <label class="field__label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="select"
                            >

                                <option value="">
                                    Semua
                                </option>

                                <?php foreach (
                                    [
                                        'hadir' => 'Hadir',
                                        'telat' => 'Telat',
                                        'alpha' => 'Alpha',
                                        'lembur' => 'Lembur',
                                        'izin' => 'Izin'
                                    ]
                                    as $val => $label
                                ): ?>

                                    <option
                                        value="<?= $val ?>"
                                        <?= $status_filter === $val ? 'selected' : '' ?>
                                    >

                                        <?= $label ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>


                    <!-- PERLU DITINJAU -->

                    <div class="col-span-12">

                        <label class="field__item mb-3">

                            <input
                                type="checkbox"
                                class="checkbox"
                                name="hanya_tinjau"
                                value="1"
                                <?= $hanya_tinjau ? 'checked' : '' ?>
                            >

                            <span class="field__label">
                                Tampilkan yang perlu ditinjau saja
                            </span>

                        </label>

                        <br>

                        <button
                            type="submit"
                            class="button button--neutral"
                        >

                            <?= ic('solar:tuning-2-linear') ?>

                            Terapkan Filter

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </section>


    <!-- ============================================================
         TABEL ABSENSI
    ============================================================= -->

    <section class="page__section">

        <div class="card">

            <?php if (empty($logs)): ?>

                <!-- EMPTY -->

                <div class="empty-state empty-state--sm">

                    <div class="empty-state__media">

                        <?= ic('solar:checklist-minimalistic-bold-duotone') ?>

                    </div>

                    <h3 class="empty-state__title">

                        Tidak ada data pada rentang ini

                    </h3>

                    <p class="empty-state__text">

                        Coba ubah filter tanggal, atau tambahkan data absensi manual.

                    </p>

                </div>

            <?php else: ?>


                <!-- ====================================================
                     TOOLBAR HAPUS
                ===================================================== -->

                <div
                    class="card__header"
                    style="
                        display:flex;
                        align-items:center;
                        justify-content:space-between;
                        gap:12px;
                        flex-wrap:wrap;
                    "
                >

                    <div>

                        <span class="card__title">

                            Data Absensi

                        </span>

                        <div
                            class="text-xs text-muted-foreground"
                            style="margin-top:4px;"
                        >

                            <?= count($logs) ?> data ditampilkan

                        </div>

                    </div>


                    <div
                        style="
                            display:flex;
                            align-items:center;
                            gap:8px;
                            flex-wrap:wrap;
                        "
                    >

                        <!-- HAPUS YANG DIPILIH -->

                        <button
                            type="submit"
                            form="formHapusBatch"
                            class="button button--sm button--ghost button--danger"
                            id="btnHapusDipilih"
                            disabled
                            onclick="return konfirmasiHapusDipilih();"
                        >

                            <?= ic('solar:trash-bin-minimalistic-linear') ?>

                            Hapus yang Dipilih
                            <span id="jumlahDipilih">(0)</span>

                        </button>


                        <!-- HAPUS SEMUA -->

                        <form
                            method="post"
                            style="display:inline;"
                            onsubmit="return konfirmasiHapusSemua();"
                        >

                            <input
                                type="hidden"
                                name="aksi"
                                value="hapus_semua"
                            >

                            <input
                                type="hidden"
                                name="redirect_qs"
                                value="<?= e($qs) ?>"
                            >

                            <button
                                type="submit"
                                class="button button--sm button--danger"
                            >

                                <?= ic('solar:trash-bin-trash-linear') ?>

                                Hapus Semua

                            </button>

                        </form>

                    </div>

                </div>


                <!-- ====================================================
                     FORM HAPUS BATCH
                ===================================================== -->

                <form
                    method="post"
                    id="formHapusBatch"
                >

                    <input
                        type="hidden"
                        name="aksi"
                        value="hapus_batch"
                    >

                    <input
                        type="hidden"
                        name="redirect_qs"
                        value="<?= e($qs) ?>"
                    >


                    <!-- =================================================
                         TABEL
                    ================================================== -->

                    <div class="table-responsive">

                        <table class="table table--hover table--align-middle">

                            <thead class="table__head--alt">

                                <tr>

                                    <!-- PILIH SEMUA -->

                                    <th
                                        style="
                                            width:50px;
                                            text-align:center;
                                        "
                                    >

                                        <input
                                            type="checkbox"
                                            class="checkbox"
                                            id="checkSemua"
                                            title="Pilih semua"
                                        >

                                    </th>

                                    <th>
                                        Tanggal
                                    </th>

                                    <th>
                                        Karyawan
                                    </th>

                                    <th>
                                        Departemen
                                    </th>

                                    <th>
                                        Jam Masuk
                                    </th>

                                    <th>
                                        Jam Pulang
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                    <th class="text-end">
                                        Aksi
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach ($logs as $l): ?>

                                    <tr>

                                        <!-- CHECKBOX DATA -->

                                        <td
                                            style="
                                                text-align:center;
                                            "
                                        >

                                            <input
                                                type="checkbox"
                                                class="checkbox checkbox-data"
                                                name="ids[]"
                                                value="<?= (int) $l['id'] ?>"
                                            >

                                        </td>


                                        <!-- TANGGAL -->

                                        <td>

                                            <?= e(
                                                tgl_indo(
                                                    $l['tanggal'],
                                                    false
                                                )
                                            ) ?>

                                        </td>


                                        <!-- KARYAWAN -->

                                        <td>

                                            <div class="font-medium">

                                                <?= e(
                                                    $l['nama']
                                                ) ?>

                                            </div>

                                            <div class="text-xs text-muted-foreground">

                                                <?= e(
                                                    $l['nik']
                                                ) ?>

                                            </div>

                                        </td>


                                        <!-- DEPARTEMEN -->

                                        <td>

                                            <?= e(
                                                $l['nama_departemen'] ?? '-'
                                            ) ?>

                                        </td>


                                        <!-- JAM MASUK -->

                                        <td>

                                            <?= jam(
                                                $l['jam_masuk_aktual']
                                            ) ?>

                                        </td>


                                        <!-- JAM PULANG -->

                                        <td>

                                            <?= jam(
                                                $l['jam_pulang_aktual']
                                            ) ?>

                                        </td>


                                        <!-- STATUS -->

                                        <td>

                                            <?= badge_status(
                                                $l['status']
                                            ) ?>


                                            <?php if (
                                                $l['status'] === 'telat'
                                            ): ?>

                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >

                                                    (
                                                    <?= (int) $l['menit_telat'] ?>
                                                    menit)

                                                </span>

                                            <?php endif; ?>


                                            <?php if (
                                                !empty($l['perlu_tinjau'])
                                            ): ?>

                                                <span
                                                    class="badge badge--soft badge--warning"
                                                >

                                                    Perlu Ditinjau

                                                </span>

                                            <?php endif; ?>

                                        </td>


                                        <!-- AKSI -->

                                        <td class="text-end">

                                            <form
                                                method="post"
                                                style="display:inline"
                                                onsubmit="return confirm('Apakah Anda yakin ingin menghapus data absensi ini?');"
                                            >

                                                <input
                                                    type="hidden"
                                                    name="aksi"
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $l['id'] ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="redirect_qs"
                                                    value="<?= e($qs) ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="button button--sm button--ghost button--danger"
                                                    title="Hapus data"
                                                >

                                                    <?= ic('solar:trash-bin-minimalistic-linear') ?>

                                                </button>

                                            </form>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                </form>

            <?php endif; ?>

        </div>

    </section>

</div>


<!-- ================================================================
     DIALOG TAMBAH ABSENSI
================================================================= -->

<div
    class="dialog"
    id="tambahAbsensi"
    data-stisla-dialog
    data-state="closed"
    role="dialog"
    aria-modal="true"
    tabindex="-1"
>

    <div
        class="dialog__backdrop"
        data-stisla-dialog-dismiss
    ></div>


    <div class="dialog__panel">

        <div class="dialog__content">

            <form method="post">

                <input
                    type="hidden"
                    name="aksi"
                    value="tambah"
                >

                <input
                    type="hidden"
                    name="redirect_qs"
                    value="<?= e($qs) ?>"
                >


                <button
                    type="button"
                    class="dialog__close"
                    data-stisla-dialog-dismiss
                    aria-label="Tutup"
                >

                    <?= ic('solar:close-circle-linear') ?>

                </button>


                <div class="dialog__header">

                    <h3 class="dialog__title">

                        Tambah Absensi Manual

                    </h3>

                </div>


                <div
                    class="dialog__body flex flex-col gap-4"
                >

                    <!-- KARYAWAN -->

                    <div class="field">

                        <label class="field__label">

                            Karyawan

                        </label>

                        <select
                            name="employee_id"
                            class="select"
                            required
                        >

                            <option value="">

                                — Pilih Karyawan —

                            </option>

                            <?php foreach (
                                $karyawan_list
                                as $k
                            ): ?>

                                <option
                                    value="<?= (int) $k['id'] ?>"
                                >

                                    <?= e($k['nama']) ?>

                                    (
                                    <?= e($k['nik']) ?>
                                    )

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- TANGGAL -->

                    <div class="field">

                        <label class="field__label">

                            Tanggal

                        </label>

                        <input
                            type="date"
                            name="tanggal"
                            class="input"
                            value="<?= e(date('Y-m-d')) ?>"
                            required
                        >

                    </div>


                    <!-- JAM -->

                    <div class="grid grid-cols-12 gap-4">

                        <div class="col-span-6">

                            <div class="field">

                                <label class="field__label">

                                    Jam Masuk

                                </label>

                                <input
                                    type="time"
                                    name="jam_masuk_aktual"
                                    class="input"
                                >

                            </div>

                        </div>


                        <div class="col-span-6">

                            <div class="field">

                                <label class="field__label">

                                    Jam Pulang

                                </label>

                                <input
                                    type="time"
                                    name="jam_pulang_aktual"
                                    class="input"
                                >

                            </div>

                        </div>

                    </div>


                    <!-- CATATAN -->

                    <div class="field">

                        <label class="field__label">

                            Catatan (opsional)

                        </label>

                        <input
                            type="text"
                            name="catatan"
                            class="input"
                            placeholder="mis: lupa absen, dicatat manual oleh admin"
                        >

                    </div>


                    <!-- INFO -->

                    <div class="alert alert--neutral">

                        <?= ic(
                            'solar:danger-triangle-bold-duotone'
                        ) ?>

                        <span class="text-sm">

                            Status (Hadir/Telat/Alpha)
                            dihitung otomatis berdasarkan
                            shift karyawan yang bersangkutan.
                            Kosongkan jam masuk kalau
                            karyawan tidak hadir (alpha).

                        </span>

                    </div>

                </div>


                <!-- FOOTER -->

                <div class="dialog__footer">

                    <button
                        type="button"
                        class="button button--ghost button--neutral"
                        data-stisla-dialog-dismiss
                    >

                        Batal

                    </button>


                    <button
                        type="submit"
                        class="button button--primary"
                    >

                        Simpan

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ================================================================
     JAVASCRIPT PILIH BANYAK / HAPUS
================================================================= -->

<script>

document.addEventListener('DOMContentLoaded', function () {

    const checkSemua =
        document.getElementById('checkSemua');

    const checkboxData =
        document.querySelectorAll('.checkbox-data');

    const btnHapusDipilih =
        document.getElementById('btnHapusDipilih');

    const jumlahDipilih =
        document.getElementById('jumlahDipilih');


    /*
    |--------------------------------------------------------------------------
    | Update tampilan tombol
    |--------------------------------------------------------------------------
    */

    function updatePilihan() {

        const jumlah =
            document.querySelectorAll(
                '.checkbox-data:checked'
            ).length;


        /*
        |--------------------------------------------------------------------------
        | Tampilkan jumlah
        |--------------------------------------------------------------------------
        */

        jumlahDipilih.textContent =
            '(' + jumlah + ')';


        /*
        |--------------------------------------------------------------------------
        | Aktif/nonaktif tombol
        |--------------------------------------------------------------------------
        */

        btnHapusDipilih.disabled =
            jumlah === 0;


        /*
        |--------------------------------------------------------------------------
        | Status checkbox "Pilih Semua"
        |--------------------------------------------------------------------------
        */

        if (jumlah === 0) {

            checkSemua.checked = false;

            checkSemua.indeterminate = false;

        } else if (
            jumlah === checkboxData.length
        ) {

            checkSemua.checked = true;

            checkSemua.indeterminate = false;

        } else {

            checkSemua.checked = false;

            checkSemua.indeterminate = true;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Tombol pilih semua
    |--------------------------------------------------------------------------
    */

    if (checkSemua) {

        checkSemua.addEventListener(
            'change',
            function () {

                checkboxData.forEach(
                    function (checkbox) {

                        checkbox.checked =
                            checkSemua.checked;

                    }
                );

                updatePilihan();
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Checkbox masing-masing data
    |--------------------------------------------------------------------------
    */

    checkboxData.forEach(
        function (checkbox) {

            checkbox.addEventListener(
                'change',
                updatePilihan
            );

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Jalankan pertama kali
    |--------------------------------------------------------------------------
    */

    updatePilihan();

});


/*
|--------------------------------------------------------------------------
| KONFIRMASI HAPUS DATA TERPILIH
|--------------------------------------------------------------------------
*/

function konfirmasiHapusDipilih() {

    const jumlah =
        document.querySelectorAll(
            '.checkbox-data:checked'
        ).length;


    if (jumlah === 0) {

        alert(
            'Silakan pilih data absensi terlebih dahulu.'
        );

        return false;
    }


    return confirm(
        'Apakah Anda yakin ingin menghapus ' +
        jumlah +
        ' data absensi yang dipilih?\n\n' +
        'Data yang sudah dihapus tidak dapat dikembalikan.'
    );
}


/*
|--------------------------------------------------------------------------
| KONFIRMASI HAPUS SEMUA
|--------------------------------------------------------------------------
*/

function konfirmasiHapusSemua() {

    return confirm(
        '⚠️ PERINGATAN!\n\n' +
        'Apakah Anda yakin ingin menghapus SEMUA data absensi?\n\n' +
        'Seluruh data pada tabel attendance_daily akan dihapus.\n\n' +
        'Data yang sudah dihapus tidak dapat dikembalikan.'
    );
}

</script>


<?php

include __DIR__ . '/includes/footer.php';

?>