<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/auth.php';

wajib_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah' || $aksi === 'edit') {

        $nik = trim($_POST['nik'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $departemen_id = $_POST['departemen_id'] !== '' ? (int) $_POST['departemen_id'] : null;
        $jabatan = trim($_POST['jabatan'] ?? '');
        $no_hp = trim($_POST['no_hp'] ?? '');
        $shift_id = $_POST['shift_id'] !== '' ? (int) $_POST['shift_id'] : null;
        $device_user_id = trim($_POST['device_user_id'] ?? '') ?: null;
        $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;

        if ($nik === '' || $nama === '') {

            flash('error', 'NIK dan Nama wajib diisi.');

        } else {

            try {

                if ($aksi === 'tambah') {

                    $pdo->prepare(
                        'INSERT INTO employees
                        (nik, nama, departemen_id, jabatan, no_hp, shift_id, device_user_id, status_aktif)
                        VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([
                        $nik,
                        $nama,
                        $departemen_id,
                        $jabatan,
                        $no_hp,
                        $shift_id,
                        $device_user_id,
                        $status_aktif
                    ]);

                    flash('success', 'Karyawan berhasil ditambahkan.');

                } else {

                    $id = (int) $_POST['id'];

                    $pdo->prepare(
                        'UPDATE employees
                         SET nik=?, nama=?, departemen_id=?, jabatan=?, no_hp=?,
                             shift_id=?, device_user_id=?, status_aktif=?
                         WHERE id=?'
                    )->execute([
                        $nik,
                        $nama,
                        $departemen_id,
                        $jabatan,
                        $no_hp,
                        $shift_id,
                        $device_user_id,
                        $status_aktif,
                        $id
                    ]);

                    flash('success', 'Data karyawan berhasil diperbarui.');
                }

            } catch (PDOException $e) {

                flash(
                    'error',
                    str_contains($e->getMessage(), 'nik') ||
                    str_contains(strtolower($e->getMessage()), 'unique')
                        ? 'NIK sudah dipakai karyawan lain.'
                        : 'Gagal menyimpan data: ' . $e->getMessage()
                );
            }
        }

    } elseif ($aksi === 'hapus') {

        $id = (int) ($_POST['id'] ?? 0);

        if ($id > 0) {
            $pdo->prepare('DELETE FROM employees WHERE id = ?')->execute([$id]);
            flash('success', 'Karyawan berhasil dihapus.');
        }

    } elseif ($aksi === 'hapus_batch') {

        $ids = $_POST['ids'] ?? [];

        if (!is_array($ids)) {
            $ids = [];
        }

        $ids = array_values(
            array_unique(
                array_filter(
                    array_map('intval', $ids),
                    fn($id) => $id > 0
                )
            )
        );

        if (empty($ids)) {

            flash('error', 'Pilih minimal satu karyawan yang ingin dihapus.');

        } else {

            try {

                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                $stmt = $pdo->prepare(
                    "DELETE FROM employees WHERE id IN ($placeholders)"
                );

                $stmt->execute($ids);

                $jumlah = $stmt->rowCount();

                flash(
                    'success',
                    $jumlah . ' karyawan berhasil dihapus.'
                );

            } catch (PDOException $e) {

                flash(
                    'error',
                    'Gagal menghapus karyawan: ' . $e->getMessage()
                );
            }
        }

    } elseif ($aksi === 'hapus_semua') {

        try {

            $stmt = $pdo->query('DELETE FROM employees');
            $jumlah = $stmt->rowCount();

            flash(
                'success',
                $jumlah . ' karyawan berhasil dihapus.'
            );

        } catch (PDOException $e) {

            flash(
                'error',
                'Gagal menghapus seluruh karyawan: ' . $e->getMessage()
            );
        }
    }

    redirect('karyawan.php');
}

$q = trim($_GET['q'] ?? '');

$sql = 'SELECT e.*, d.nama_departemen, s.nama_shift
        FROM employees e
        LEFT JOIN departments d ON d.id = e.departemen_id
        LEFT JOIN shifts s ON s.id = e.shift_id';

$params = [];

if ($q !== '') {

    $sql .= ' WHERE e.nama LIKE ? OR e.nik LIKE ?';

    $params = [
        "%$q%",
        "%$q%"
    ];
}

$sql .= ' ORDER BY e.nama';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$karyawan = $stmt->fetchAll();

$departemen_list = $pdo
    ->query('SELECT * FROM departments ORDER BY nama_departemen')
    ->fetchAll();

$shift_list = $pdo
    ->query('SELECT * FROM shifts ORDER BY nama_shift')
    ->fetchAll();

$page_title = 'Karyawan';
$nav_active = 'karyawan';

include __DIR__ . '/includes/header.php';


function form_karyawan_fields(
    array $departemen_list,
    array $shift_list,
    ?array $data = null
): void {

    $data = $data ?? [
        'nik' => '',
        'nama' => '',
        'departemen_id' => '',
        'jabatan' => '',
        'no_hp' => '',
        'shift_id' => '',
        'device_user_id' => '',
        'status_aktif' => 1
    ];

    ?>

    <div class="grid grid-cols-12 gap-4">

        <div class="col-span-6">
            <div class="field">

                <label class="field__label">NIK</label>

                <input
                    type="text"
                    name="nik"
                    class="input"
                    value="<?= e($data['nik']) ?>"
                    required
                >

            </div>
        </div>

        <div class="col-span-6">
            <div class="field">

                <label class="field__label">Nama Lengkap</label>

                <input
                    type="text"
                    name="nama"
                    class="input"
                    value="<?= e($data['nama']) ?>"
                    required
                >

            </div>
        </div>

    </div>


    <div class="grid grid-cols-12 gap-4">

        <div class="col-span-6">
            <div class="field">

                <label class="field__label">Departemen</label>

                <select name="departemen_id" class="select">

                    <option value="">— Pilih —</option>

                    <?php foreach ($departemen_list as $d): ?>

                        <option
                            value="<?= (int) $d['id'] ?>"
                            <?= (string) $data['departemen_id'] === (string) $d['id'] ? 'selected' : '' ?>
                        >
                            <?= e($d['nama_departemen']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>
        </div>


        <div class="col-span-6">
            <div class="field">

                <label class="field__label">Jabatan</label>

                <input
                    type="text"
                    name="jabatan"
                    class="input"
                    value="<?= e($data['jabatan']) ?>"
                >

            </div>
        </div>

    </div>


    <div class="grid grid-cols-12 gap-4">

        <div class="col-span-6">
            <div class="field">

                <label class="field__label">No. HP</label>

                <input
                    type="text"
                    name="no_hp"
                    class="input"
                    value="<?= e($data['no_hp']) ?>"
                >

            </div>
        </div>


        <div class="col-span-6">
            <div class="field">

                <label class="field__label">Shift Kerja</label>

                <select name="shift_id" class="select">

                    <option value="">— Pilih —</option>

                    <?php foreach ($shift_list as $s): ?>

                        <option
                            value="<?= (int) $s['id'] ?>"
                            <?= (string) $data['shift_id'] === (string) $s['id'] ? 'selected' : '' ?>
                        >
                            <?= e($s['nama_shift']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>
        </div>

    </div>


    <div class="field">

        <label class="field__label">
            ID di Mesin Fingerprint (opsional)
        </label>

        <input
            type="text"
            name="device_user_id"
            class="input"
            value="<?= e($data['device_user_id']) ?>"
            placeholder="Diisi nanti setelah integrasi mesin aktif"
        >

        <span class="field__description">
            Kosongkan dulu kalau mesin belum terhubung ke sistem.
        </span>

    </div>


    <div class="field__item">

        <input
            type="checkbox"
            class="checkbox"
            name="status_aktif"
            id="status_aktif<?= e($data['nik']) ?>"
            <?= $data['status_aktif'] ? 'checked' : '' ?>
        >

        <label
            class="field__label"
            for="status_aktif<?= e($data['nik']) ?>"
        >
            Karyawan aktif
        </label>

    </div>

    <?php
}

?>


<header class="page__header">

    <div class="page__headline">

        <h1 class="page__title">
            Karyawan
        </h1>

        <p class="page__description">
            Kelola data seluruh karyawan PT Jafran Indonesia.
        </p>

    </div>


    <div class="page__action">

        <button
            type="button"
            class="button button--primary"
            data-stisla-dialog-trigger="tambahKaryawan"
        >
            <?= ic('solar:add-circle-linear') ?>
            Tambah Karyawan
        </button>

    </div>

</header>


<div class="page__body">

    <?php include __DIR__ . '/includes/flash.php'; ?>


    <section class="page__section">

        <div class="card">


            <!-- HEADER CARD -->

            <div class="card__header flex-wrap">

                <div class="flex items-center gap-2">

                    <?php if (!empty($karyawan)): ?>

                        <label
                            class="flex items-center gap-2"
                            style="cursor:pointer;"
                        >

                            <input
                                type="checkbox"
                                class="checkbox"
                                id="pilihSemuaKaryawan"
                            >

                            <span>
                                Pilih Semua
                            </span>

                        </label>


                        <button
                            type="button"
                            id="btnHapusTerpilih"
                            class="button button--sm button--danger"
                            data-stisla-dialog-trigger="hapusTerpilihKaryawan"
                            disabled
                        >
                            <?= ic('solar:trash-bin-minimalistic-linear') ?>
                            Hapus Terpilih
                        </button>


                        <button
                            type="button"
                            class="button button--sm button--ghost button--danger"
                            data-stisla-dialog-trigger="hapusSemuaKaryawan"
                        >
                            <?= ic('solar:trash-bin-minimalistic-linear') ?>
                            Hapus Semua
                        </button>

                    <?php endif; ?>

                </div>


                <form
                    class="input-group ms-auto w-full md:w-72 mb-4 md:mb-0"
                    role="search"
                    method="get"
                >

                    <span class="input-group__text">
                        <?= ic('solar:magnifer-linear') ?>
                    </span>

                    <input
                        type="search"
                        name="q"
                        class="input"
                        placeholder="Cari nama / NIK…"
                        value="<?= e($q) ?>"
                    >

                </form>

            </div>


            <?php if (empty($karyawan)): ?>

                <div class="empty-state empty-state--sm">

                    <div class="empty-state__media">
                        <?= ic('solar:users-group-rounded-bold-duotone') ?>
                    </div>

                    <h3 class="empty-state__title">
                        Belum ada data karyawan
                    </h3>

                    <p class="empty-state__text">
                        Tambahkan karyawan pertama lewat tombol di atas.
                    </p>

                </div>

            <?php else: ?>


                <div class="table-responsive">

                    <table class="table table--hover table--align-middle">

                        <thead class="table__head--alt">

                            <tr>

                                <th style="width: 50px;">
                                    #
                                </th>

                                <th>
                                    NIK
                                </th>

                                <th>
                                    Nama
                                </th>

                                <th>
                                    Departemen
                                </th>

                                <th>
                                    Jabatan
                                </th>

                                <th>
                                    Shift
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

                            <?php foreach ($karyawan as $k): ?>

                                <tr>

                                    <td>

                                        <input
                                            type="checkbox"
                                            class="checkbox checkbox-karyawan"
                                            name="ids[]"
                                            value="<?= (int) $k['id'] ?>"
                                            data-nama="<?= e($k['nama']) ?>"
                                        >

                                    </td>


                                    <td>
                                        <?= e($k['nik']) ?>
                                    </td>


                                    <td class="font-medium">
                                        <?= e($k['nama']) ?>
                                    </td>


                                    <td>
                                        <?= e($k['nama_departemen'] ?? '-') ?>
                                    </td>


                                    <td>
                                        <?= e($k['jabatan'] ?: '-') ?>
                                    </td>


                                    <td>
                                        <?= e($k['nama_shift'] ?? '-') ?>
                                    </td>


                                    <td>

                                        <?=
                                            $k['status_aktif']
                                                ? '<span class="badge badge--soft badge--success">Aktif</span>'
                                                : '<span class="badge badge--soft badge--neutral">Nonaktif</span>'
                                        ?>

                                    </td>


                                    <td class="text-end">

                                        <button
                                            type="button"
                                            class="button button--sm button--ghost button--neutral"
                                            data-stisla-dialog-trigger="editKar<?= (int) $k['id'] ?>"
                                        >
                                            <?= ic('solar:pen-2-linear') ?>
                                        </button>


                                        <button
                                            type="button"
                                            class="button button--sm button--ghost button--danger"
                                            data-stisla-dialog-trigger="hapusKar<?= (int) $k['id'] ?>"
                                        >
                                            <?= ic('solar:trash-bin-minimalistic-linear') ?>
                                        </button>

                                    </td>

                                </tr>


                                <!-- DIALOG EDIT -->

                                <div
                                    class="dialog"
                                    id="editKar<?= (int) $k['id'] ?>"
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
                                                    value="edit"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $k['id'] ?>"
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
                                                        Edit Karyawan
                                                    </h3>

                                                </div>


                                                <div class="dialog__body flex flex-col gap-4">

                                                    <?php
                                                    form_karyawan_fields(
                                                        $departemen_list,
                                                        $shift_list,
                                                        $k
                                                    );
                                                    ?>

                                                </div>


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


                                <!-- DIALOG HAPUS SATU -->

                                <div
                                    class="dialog dialog--sm"
                                    id="hapusKar<?= (int) $k['id'] ?>"
                                    data-stisla-dialog
                                    data-state="closed"
                                    role="alertdialog"
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
                                                    value="hapus"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="id"
                                                    value="<?= (int) $k['id'] ?>"
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
                                                        Hapus Karyawan?
                                                    </h3>

                                                </div>


                                                <div class="dialog__body">

                                                    <p class="text-muted-foreground">

                                                        Yakin ingin menghapus
                                                        <strong>
                                                            <?= e($k['nama']) ?>
                                                        </strong>?

                                                        Seluruh riwayat absensinya
                                                        juga akan terhapus.

                                                    </p>

                                                </div>


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
                                                        class="button button--danger"
                                                    >
                                                        Hapus
                                                    </button>

                                                </div>

                                            </form>

                                        </div>

                                    </div>

                                </div>


                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </section>

</div>


<!-- DIALOG TAMBAH KARYAWAN -->

<div
    class="dialog"
    id="tambahKaryawan"
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
                        Tambah Karyawan
                    </h3>

                </div>


                <div class="dialog__body flex flex-col gap-4">

                    <?php
                    form_karyawan_fields(
                        $departemen_list,
                        $shift_list
                    );
                    ?>

                </div>


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


<!-- DIALOG HAPUS TERPILIH -->

<div
    class="dialog dialog--sm"
    id="hapusTerpilihKaryawan"
    data-stisla-dialog
    data-state="closed"
    role="alertdialog"
    aria-modal="true"
    tabindex="-1"
>

    <div
        class="dialog__backdrop"
        data-stisla-dialog-dismiss
    ></div>


    <div class="dialog__panel">

        <div class="dialog__content">

            <form
                method="post"
                id="formHapusTerpilih"
            >

                <input
                    type="hidden"
                    name="aksi"
                    value="hapus_batch"
                >

                <div id="containerIdTerpilih"></div>


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
                        Hapus Karyawan Terpilih?
                    </h3>

                </div>


                <div class="dialog__body">

                    <p class="text-muted-foreground">

                        Kamu akan menghapus
                        <strong id="jumlahKaryawanTerpilih">
                            0
                        </strong>
                        karyawan yang dipilih.

                        <br><br>

                        <strong>
                            Data riwayat absensi karyawan tersebut juga akan terhapus.
                        </strong>

                        <br><br>

                        Tindakan ini tidak dapat dibatalkan.

                    </p>

                </div>


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
                        class="button button--danger"
                    >
                        Hapus Karyawan
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- DIALOG HAPUS SEMUA -->

<div
    class="dialog dialog--sm"
    id="hapusSemuaKaryawan"
    data-stisla-dialog
    data-state="closed"
    role="alertdialog"
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
                    value="hapus_semua"
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
                        Hapus Semua Karyawan?
                    </h3>

                </div>


                <div class="dialog__body">

                    <p class="text-muted-foreground">

                        Kamu akan menghapus
                        <strong>seluruh data karyawan</strong>
                        yang ada di sistem.

                        <br><br>

                        <strong>
                            Seluruh riwayat absensi karyawan juga akan ikut terhapus.
                        </strong>

                        <br><br>

                        Tindakan ini tidak dapat dibatalkan.

                    </p>

                </div>


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
                        class="button button--danger"
                    >
                        Hapus Semua
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const masterCheckbox =
        document.getElementById('pilihSemuaKaryawan');

    const checkboxes =
        document.querySelectorAll('.checkbox-karyawan');

    const btnHapusTerpilih =
        document.getElementById('btnHapusTerpilih');

    const jumlahTerpilih =
        document.getElementById('jumlahKaryawanTerpilih');

    const containerIdTerpilih =
        document.getElementById('containerIdTerpilih');


    function updateSelection() {

        const selected =
            document.querySelectorAll(
                '.checkbox-karyawan:checked'
            );

        const jumlah = selected.length;


        if (btnHapusTerpilih) {

            btnHapusTerpilih.disabled =
                jumlah === 0;

        }


        if (jumlahTerpilih) {

            jumlahTerpilih.textContent =
                jumlah;

        }


        if (masterCheckbox) {

            masterCheckbox.checked =
                checkboxes.length > 0 &&
                jumlah === checkboxes.length;

            masterCheckbox.indeterminate =
                jumlah > 0 &&
                jumlah < checkboxes.length;

        }

    }


    if (masterCheckbox) {

        masterCheckbox.addEventListener(
            'change',
            function () {

                checkboxes.forEach(function (checkbox) {

                    checkbox.checked =
                        masterCheckbox.checked;

                });

                updateSelection();

            }
        );

    }


    checkboxes.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            updateSelection
        );

    });


    /*
     * Saat dialog "Hapus Terpilih" dibuka,
     * salin ID checkbox yang dipilih ke form POST.
     */

    if (btnHapusTerpilih) {

        btnHapusTerpilih.addEventListener(
            'click',
            function () {

                const selected =
                    document.querySelectorAll(
                        '.checkbox-karyawan:checked'
                    );


                if (!containerIdTerpilih) {
                    return;
                }


                containerIdTerpilih.innerHTML = '';


                selected.forEach(function (checkbox) {

                    const input =
                        document.createElement('input');

                    input.type = 'hidden';

                    input.name = 'ids[]';

                    input.value = checkbox.value;

                    containerIdTerpilih.appendChild(input);

                });


                if (jumlahTerpilih) {

                    jumlahTerpilih.textContent =
                        selected.length;

                }

            }
        );

    }


    updateSelection();

});

</script>


<?php include __DIR__ . '/includes/footer.php'; ?>