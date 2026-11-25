<?php

namespace app\tests\Integration;

use app\components\http\HttpCode;
use app\models\Tagihan;
use app\services\PendaftaranService;

final class PendaftaranServiceTest extends TestCase
{
    public function testDaftarMembuatKunjunganAntreanDanTagihanPendaftaran(): void
    {
        $pasien = $this->buatPasien();
        $jadwal = $this->jadwalHariIni();

        $kunjungan = (new PendaftaranService())->daftar(['pasien_id' => $pasien->id, 'jadwal_id' => $jadwal->id], null);

        $this->assertSame('menunggu', $kunjungan->status);
        $this->assertGreaterThan(0, $kunjungan->no_antrean);

        $tagihan = Tagihan::findOne(['kunjungan_id' => $kunjungan->id]);
        $this->assertSame((float) $jadwal->poli->tarif_pendaftaran, (float) $tagihan->total_tagihan);
        $this->assertSame('belum_bayar', $tagihan->status);
    }

    public function testPasienYangSamaTidakBisaDidaftarkanDuaKali(): void
    {
        $pasien = $this->buatPasien();
        $jadwal = $this->jadwalHariIni();
        $service = new PendaftaranService();

        $service->daftar(['pasien_id' => $pasien->id, 'jadwal_id' => $jadwal->id], null);

        $this->assertApiException(HttpCode::CONFLICT, fn () => $service->daftar(['pasien_id' => $pasien->id, 'jadwal_id' => $jadwal->id], null));
    }

    public function testNomorAntreanBerurutanUntukPasienBerbeda(): void
    {
        $jadwal = $this->jadwalHariIni();
        $service = new PendaftaranService();

        $a = $service->daftar(['pasien_id' => $this->buatPasien('A')->id, 'jadwal_id' => $jadwal->id], null);
        $b = $service->daftar(['pasien_id' => $this->buatPasien('B')->id, 'jadwal_id' => $jadwal->id], null);

        $this->assertSame($a->no_antrean + 1, $b->no_antrean);
    }

    public function testTanggalLampauDitolak(): void
    {
        $this->assertApiException(HttpCode::UNPROCESSABLE, fn () => (new PendaftaranService())->daftar([
            'pasien_id' => $this->buatPasien()->id,
            'jadwal_id' => $this->jadwalHariIni()->id,
            'tanggal' => date('Y-m-d', strtotime('-1 day')),
        ], null));
    }
}
