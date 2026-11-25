<?php

namespace app\tests\Integration;

use app\exceptions\ApiException;
use app\models\JadwalPraktik;
use app\models\Pasien;
use Yii;
use yii\db\Transaction;

/**
 * Setiap test berjalan di dalam transaksi yang di-rollback, jadi data seed tidak berubah.
 * Transaksi di dalam service otomatis menjadi savepoint.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private Transaction $transaction;

    protected function setUp(): void
    {
        $this->transaction = Yii::$app->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->transaction->rollBack();
    }

    protected function buatPasien(string $nama = 'Pasien Test'): Pasien
    {
        $pasien = new Pasien(['nama' => $nama, 'jenis_kelamin' => 'P', 'tgl_lahir' => '1990-01-01']);
        $pasien->save(false);

        return $pasien;
    }

    protected function jadwalHariIni(string $nip = '198501122010011001'): JadwalPraktik
    {
        return JadwalPraktik::find()
            ->innerJoinWith('dokter d')
            ->where(['d.nip' => $nip, 'jadwal_praktik.hari' => (int) date('N')])
            ->one();
    }

    protected function assertApiException(int $status, callable $callback): ApiException
    {
        try {
            $callback();
        } catch (ApiException $e) {
            $this->assertSame($status, $e->statusCode, $e->getMessage());

            return $e;
        }

        $this->fail("ApiException {$status} tidak dilempar.");
    }
}
