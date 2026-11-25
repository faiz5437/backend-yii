<?php

namespace app\services;

use Yii;

/**
 * Penomoran dokumen memakai fungsi next_nomor() di database.
 * Baris counter terkunci sampai transaksi selesai, sehingga nomor tidak pernah dobel
 * dan ikut dibatalkan bila transaksi rollback.
 */
final class NomorService
{
    public static function noRekamMedis(): string
    {
        return 'RM-' . str_pad((string) self::next('RM'), 6, '0', STR_PAD_LEFT);
    }

    public static function noKunjungan(string $tanggal): string
    {
        return self::harian('KJ', $tanggal);
    }

    public static function noResep(): string
    {
        return self::harian('RSP', date('Y-m-d'));
    }

    public static function noLabOrder(): string
    {
        return self::harian('LAB', date('Y-m-d'));
    }

    public static function noInvoice(): string
    {
        return self::bulanan('INV');
    }

    public static function noKwitansi(): string
    {
        return self::bulanan('KW');
    }

    public static function noAntrean(string $poliId, string $tanggal): int
    {
        return (int) Yii::$app->db->createCommand('SELECT next_antrean(:poli, :tanggal)', [
            ':poli' => $poliId,
            ':tanggal' => $tanggal,
        ])->queryScalar();
    }

    private static function harian(string $prefix, string $tanggal): string
    {
        $tgl = date('Ymd', strtotime($tanggal));

        return sprintf('%s-%s-%04d', $prefix, $tgl, self::next("{$prefix}-{$tgl}"));
    }

    private static function bulanan(string $prefix): string
    {
        return sprintf('%s/%s/%05d', $prefix, date('Y/m'), self::next($prefix . '-' . date('Ym')));
    }

    private static function next(string $kunci): int
    {
        return (int) Yii::$app->db->createCommand('SELECT next_nomor(:kunci)', [':kunci' => $kunci])->queryScalar();
    }
}
