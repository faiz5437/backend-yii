<?php

namespace app\tests\Integration;

use app\components\http\HttpCode;
use app\models\Kunjungan;
use app\models\Poli;
use app\services\AntreanService;
use app\services\PendaftaranService;
use app\services\PengaturanService;
use ReflectionProperty;

final class AntreanServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        (new ReflectionProperty(PengaturanService::class, 'cache'))->setValue(null, null);
    }

    public function testEstimasiBertambahSesuaiDurasiUntukSetiapAntreanDiDepan(): void
    {
        $jadwal = $this->jadwalHariIni();
        $durasi = PengaturanService::get(PengaturanService::DURASI_LAYANAN);
        $service = new PendaftaranService();

        $pertama = $service->daftar(['pasien_id' => $this->buatPasien('A')->id, 'jadwal_id' => $jadwal->id], null);
        $kedua = $service->daftar(['pasien_id' => $this->buatPasien('B')->id, 'jadwal_id' => $jadwal->id], null);

        $antrean = new AntreanService();
        $a = $antrean->estimasi($pertama);
        $b = $antrean->estimasi($kedua);

        $this->assertSame($b['antrean_di_depan'], $a['antrean_di_depan'] + 1);
        $selisih = strtotime($b['estimasi_panggil_at']) - strtotime($a['estimasi_panggil_at']);
        $this->assertEqualsWithDelta($durasi * 60, $selisih, 2);
    }

    public function testDurasiPoliMenggantikanPengaturanUmum(): void
    {
        $jadwal = $this->jadwalHariIni();
        Poli::updateAll(['durasi_layanan_menit' => 12], ['id' => $jadwal->poli_id]);

        $kunjungan = (new PendaftaranService())->daftar(['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $jadwal->id], null);

        $this->assertSame(12, (new AntreanService())->estimasi(Kunjungan::findOne($kunjungan->id))['durasi_menit']);
    }

    public function testPasienYangSudahDipanggilTidakDihitungSebagaiAntreanDiDepan(): void
    {
        $jadwal = $this->jadwalHariIni();
        $service = new PendaftaranService();

        $pertama = $service->daftar(['pasien_id' => $this->buatPasien('A')->id, 'jadwal_id' => $jadwal->id], null);
        $kedua = $service->daftar(['pasien_id' => $this->buatPasien('B')->id, 'jadwal_id' => $jadwal->id], null);
        $sebelum = (new AntreanService())->estimasi($kedua)['antrean_di_depan'];

        $service->panggil($pertama);

        $this->assertSame($sebelum - 1, (new AntreanService())->estimasi($kedua)['antrean_di_depan']);
    }

    public function testPengaturanDurasiTersimpanDanDivalidasi(): void
    {
        $this->assertApiException(HttpCode::UNPROCESSABLE, fn () => PengaturanService::update(['durasi_layanan_menit' => 0, 'anjungan_aktif' => true], null));

        $hasil = PengaturanService::update(['durasi_layanan_menit' => 20, 'anjungan_aktif' => false], null);

        $this->assertSame(['durasi_layanan_menit' => 20, 'anjungan_aktif' => false], $hasil);
        $this->assertSame(20, PengaturanService::get(PengaturanService::DURASI_LAYANAN));
    }

    public function testPendaftaranAnjunganDicatatSebagaiSumberAnjungan(): void
    {
        $kunjungan = (new PendaftaranService())->daftar(
            ['pasien_id' => $this->buatPasien()->id, 'jadwal_id' => $this->jadwalHariIni()->id],
            null,
            Kunjungan::SUMBER_ANJUNGAN
        );

        $this->assertSame('anjungan', Kunjungan::findOne($kunjungan->id)->sumber_daftar);
    }
}
