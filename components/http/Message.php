<?php

namespace app\components\http;

final class Message
{
    public const SUCCESS = 'Berhasil';
    public const FETCHED = 'Data berhasil diambil';
    public const CREATED = 'Data berhasil disimpan';
    public const UPDATED = 'Data berhasil diperbarui';
    public const DELETED = 'Data berhasil dihapus';
    public const DEACTIVATED = 'Data berhasil dinonaktifkan';

    public const BAD_REQUEST = 'Permintaan tidak valid';
    public const VALIDATION_FAILED = 'Data yang dikirim belum valid';
    public const NOT_FOUND = 'Data tidak ditemukan';
    public const ROUTE_NOT_FOUND = 'Endpoint tidak ditemukan';
    public const METHOD_NOT_ALLOWED = 'Metode HTTP tidak diizinkan untuk endpoint ini';
    public const CONFLICT = 'Data bentrok dengan data yang sudah ada';
    public const DUPLICATE = 'Data yang sama sudah terdaftar';
    public const IN_USE = 'Data masih digunakan oleh data lain sehingga tidak dapat dihapus';
    public const DATA_CHANGED = 'Data sudah diubah oleh pengguna lain. Muat ulang data lalu coba lagi';
    public const SERVER_ERROR = 'Terjadi kesalahan pada server';
    public const SERVICE_UNAVAILABLE = 'Layanan sedang tidak tersedia';

    public const UNAUTHORIZED = 'Sesi tidak valid atau sudah berakhir. Silakan login kembali';
    public const FORBIDDEN = 'Anda tidak memiliki akses ke fitur ini';
    public const LOGIN_SUCCESS = 'Login berhasil';
    public const LOGIN_FAILED = 'Username atau password salah';
    public const LOGOUT_SUCCESS = 'Logout berhasil';
    public const ACCOUNT_INACTIVE = 'Akun tidak aktif. Hubungi administrator';
    public const TOKEN_REFRESHED = 'Sesi berhasil diperbarui';
    public const REFRESH_TOKEN_INVALID = 'Sesi login tidak valid. Silakan login kembali';
    public const SERVICE_KEY_INVALID = 'Service key tidak valid';

    public const PASIEN_SUDAH_TERDAFTAR = 'Pasien sudah terdaftar di poli ini pada tanggal yang sama';
    public const JADWAL_TIDAK_SESUAI = 'Jadwal praktik tidak tersedia pada tanggal yang dipilih';
    public const JADWAL_BENTROK = 'Jadwal dokter bentrok dengan jadwal lain di hari yang sama';
    public const KUOTA_PENUH = 'Kuota jadwal praktik sudah penuh';
    public const TANGGAL_LAMPAU = 'Tanggal kunjungan tidak boleh sebelum hari ini';
    public const KUNJUNGAN_DIDAFTARKAN = 'Pendaftaran berhasil';
    public const KUNJUNGAN_DIPANGGIL = 'Pasien dipanggil';
    public const KUNJUNGAN_DIBATALKAN = 'Kunjungan dibatalkan';
    public const KUNJUNGAN_SELESAI = 'Pemeriksaan selesai';
    public const KUNJUNGAN_STATUS_INVALID = 'Status kunjungan tidak memungkinkan aksi ini';
    public const KUNJUNGAN_BUKAN_PASIEN_ANDA = 'Kunjungan ini bukan pasien Anda';
    public const KUNJUNGAN_SUDAH_DIBAYAR = 'Kunjungan yang sudah memiliki pembayaran tidak dapat dibatalkan';
    public const PEMERIKSAAN_BELUM_LENGKAP = 'Isi pemeriksaan dan diagnosis utama sebelum menyelesaikan kunjungan';
    public const DIAGNOSIS_UTAMA_WAJIB = 'Harus ada tepat satu diagnosis utama';

    public const STOK_TIDAK_CUKUP = 'Stok obat tidak mencukupi';
    public const STOK_DISESUAIKAN = 'Stok obat berhasil diperbarui';
    public const RESEP_DIBUAT = 'Resep berhasil dibuat';
    public const RESEP_DIPROSES = 'Resep sedang diproses';
    public const RESEP_DISERAHKAN = 'Obat berhasil diserahkan';
    public const RESEP_DIBATALKAN = 'Resep dibatalkan';
    public const RESEP_STATUS_INVALID = 'Status resep tidak memungkinkan aksi ini';

    public const LAB_ORDER_DIBUAT = 'Permintaan laboratorium berhasil dibuat';
    public const LAB_ORDER_DIPROSES = 'Permintaan laboratorium sedang diproses';
    public const LAB_HASIL_DISIMPAN = 'Hasil pemeriksaan berhasil disimpan';
    public const LAB_ORDER_STATUS_INVALID = 'Status permintaan laboratorium tidak memungkinkan aksi ini';

    public const TAGIHAN_LUNAS = 'Tagihan sudah lunas';
    public const TAGIHAN_TERKUNCI = 'Tagihan sudah lunas. Koreksi hanya dapat dilakukan oleh petugas berwenang';
    public const TAGIHAN_PERLU_REFUND = 'Total tagihan baru lebih kecil dari jumlah yang sudah dibayar. Lakukan refund terlebih dahulu';
    public const PEMBAYARAN_BERHASIL = 'Pembayaran berhasil dicatat';
    public const PEMBAYARAN_PENDING = 'Pembayaran menunggu konfirmasi';
    public const PEMBAYARAN_DIKONFIRMASI = 'Pembayaran berhasil dikonfirmasi';
    public const PEMBAYARAN_SUDAH_TERCATAT = 'Pembayaran ini sudah tercatat sebelumnya';
    public const PEMBAYARAN_MELEBIHI_SISA = 'Jumlah pembayaran melebihi sisa tagihan';
    public const PEMBAYARAN_GAGAL = 'Pembayaran sudah dinyatakan gagal';
    public const UANG_KURANG = 'Uang yang diterima kurang dari jumlah pembayaran';
    public const IDEMPOTENCY_KEY_WAJIB = 'Header Idempotency-Key wajib diisi dengan UUID';
    public const IDEMPOTENCY_KEY_BENTROK = 'Idempotency-Key sudah dipakai untuk tagihan lain';

    public const PENGATURAN_DISIMPAN = 'Pengaturan berhasil disimpan';
    public const ANJUNGAN_NONAKTIF = 'Anjungan pendaftaran mandiri sedang tidak aktif. Silakan mendaftar di loket';
    public const ANJUNGAN_PASIEN_TIDAK_DITEMUKAN = 'Pasien tidak ditemukan. Periksa kembali nomor rekam medis atau NIK, atau daftar sebagai pasien baru';
    public const ANJUNGAN_PASIEN_BARU = 'Pasien baru berhasil didaftarkan';
    public const TOO_MANY_REQUESTS = 'Terlalu banyak permintaan. Coba lagi beberapa saat lagi';

    public const USER_TIDAK_BISA_HAPUS_DIRI = 'Anda tidak dapat menonaktifkan akun sendiri';
}
