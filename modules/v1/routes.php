<?php

/**
 * Routing API versi 1: URL /api/v1/... diarahkan ke controller di modul v1.
 */

use yii\rest\UrlRule;

$id = '<id:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}>';
$tid = '<tid:[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}>';

return [
    'GET api/v1/health' => 'v1/site/health',

    'POST api/v1/auth/login' => 'v1/auth/login',
    'POST api/v1/auth/refresh' => 'v1/auth/refresh',
    'POST api/v1/auth/logout' => 'v1/auth/logout',
    'GET api/v1/auth/me' => 'v1/auth/me',

    "GET api/v1/pasien/{$id}/riwayat" => 'v1/pasien/riwayat',
    "POST api/v1/obat/{$id}/stok" => 'v1/obat/stok',
    "GET api/v1/obat/{$id}/mutasi" => 'v1/obat/mutasi',
    'GET api/v1/jadwal-praktik/tersedia' => 'v1/jadwal-praktik/tersedia',
    'GET api/v1/pasien/no-rm/<noRm:[A-Za-z0-9-]+>' => 'v1/pasien/no-rm',
    'GET api/v1/roles' => 'v1/user/roles',

    'GET api/v1/pengaturan' => 'v1/pengaturan/index',
    'PUT api/v1/pengaturan' => 'v1/pengaturan/update',

    'GET api/v1/anjungan/info' => 'v1/anjungan/info',
    'GET api/v1/anjungan/jadwal' => 'v1/anjungan/jadwal',
    'POST api/v1/anjungan/pasien' => 'v1/anjungan/pasien',
    'POST api/v1/anjungan/pasien-baru' => 'v1/anjungan/pasien-baru',
    'POST api/v1/anjungan/daftar' => 'v1/anjungan/daftar',
    "GET api/v1/anjungan/tiket/{$id}" => 'v1/anjungan/tiket',

    [
        'class' => UrlRule::class,
        'prefix' => 'api/v1',
        'pluralize' => false,
        'tokens' => ['{id}' => $id],
        'controller' => [
            'pasien' => 'v1/pasien',
            'poli' => 'v1/poli',
            'tenaga-medis' => 'v1/tenaga-medis',
            'jadwal-praktik' => 'v1/jadwal-praktik',
            'tindakan' => 'v1/tindakan',
            'obat' => 'v1/obat',
            'jenis-lab' => 'v1/jenis-lab',
            'icd10' => 'v1/icd10',
            'users' => 'v1/user',
        ],
    ],

    'GET api/v1/antrean/ringkasan' => 'v1/kunjungan/ringkasan',
    'GET api/v1/kunjungan' => 'v1/kunjungan/index',
    'POST api/v1/kunjungan' => 'v1/kunjungan/create',
    "GET api/v1/kunjungan/{$id}" => 'v1/kunjungan/view',
    "GET api/v1/kunjungan/{$id}/estimasi" => 'v1/kunjungan/estimasi',
    "POST api/v1/kunjungan/{$id}/panggil" => 'v1/kunjungan/panggil',
    "POST api/v1/kunjungan/{$id}/batal" => 'v1/kunjungan/batal',

    "PUT api/v1/kunjungan/{$id}/pemeriksaan" => 'v1/pemeriksaan/simpan',
    "PUT api/v1/kunjungan/{$id}/diagnosis" => 'v1/pemeriksaan/diagnosis',
    "POST api/v1/kunjungan/{$id}/tindakan" => 'v1/pemeriksaan/tambah-tindakan',
    "POST api/v1/kunjungan/{$id}/tindakan/{$tid}/batal" => 'v1/pemeriksaan/batal-tindakan',
    "POST api/v1/kunjungan/{$id}/selesai" => 'v1/pemeriksaan/selesai',
    "POST api/v1/kunjungan/{$id}/resep" => 'v1/resep/create',
    "POST api/v1/kunjungan/{$id}/lab-order" => 'v1/lab-order/create',

    'GET api/v1/resep' => 'v1/resep/index',
    "GET api/v1/resep/{$id}" => 'v1/resep/view',
    "POST api/v1/resep/{$id}/proses" => 'v1/resep/proses',
    "POST api/v1/resep/{$id}/serahkan" => 'v1/resep/serahkan',
    "POST api/v1/resep/{$id}/batal" => 'v1/resep/batal',

    'GET api/v1/lab-order' => 'v1/lab-order/index',
    "GET api/v1/lab-order/{$id}" => 'v1/lab-order/view',
    "POST api/v1/lab-order/{$id}/proses" => 'v1/lab-order/proses',
    "POST api/v1/lab-order/{$id}/hasil" => 'v1/lab-order/hasil',

    'GET api/v1/tagihan' => 'v1/tagihan/index',
    "GET api/v1/tagihan/{$id}" => 'v1/tagihan/view',
    "POST api/v1/tagihan/{$id}/bayar" => 'v1/tagihan/bayar',

    'GET api/v1/pembayaran' => 'v1/pembayaran/index',
    "GET api/v1/pembayaran/{$id}" => 'v1/pembayaran/view',
    "POST api/v1/pembayaran/{$id}/konfirmasi" => 'v1/pembayaran/konfirmasi',

    'POST api/v1/internal/hasil-lab' => 'v1/internal/hasil-lab',
];
