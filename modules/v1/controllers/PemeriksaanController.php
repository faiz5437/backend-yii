<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Kunjungan;
use app\services\PemeriksaanService;

class PemeriksaanController extends ApiController
{
    public function __construct($id, $module, private readonly PemeriksaanService $pemeriksaan = new PemeriksaanService(), $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function permissions(): array
    {
        return ['*' => 'periksaPasien'];
    }

    protected function verbs(): array
    {
        return [
            'simpan' => ['PUT'],
            'diagnosis' => ['PUT'],
            'tambah-tindakan' => ['POST'],
            'batal-tindakan' => ['POST'],
            'selesai' => ['POST'],
        ];
    }

    public function actionSimpan(string $id): array
    {
        $pemeriksaan = $this->pemeriksaan->simpanPemeriksaan($this->kunjungan($id), $this->body());

        return ResponseHelper::success($pemeriksaan, Message::UPDATED);
    }

    public function actionDiagnosis(string $id): array
    {
        $diagnosis = $this->pemeriksaan->simpanDiagnosis($this->kunjungan($id), $this->body()['items'] ?? []);

        return ResponseHelper::success($diagnosis, Message::UPDATED);
    }

    public function actionTambahTindakan(string $id): array
    {
        $tindakan = $this->pemeriksaan->tambahTindakan($this->kunjungan($id), $this->body());

        return ResponseHelper::created($tindakan);
    }

    public function actionBatalTindakan(string $id, string $tid): array
    {
        $tindakan = $this->pemeriksaan->batalTindakan(
            $this->kunjungan($id),
            $tid,
            (string) ($this->body()['alasan'] ?? ''),
            $this->userId(),
            $this->can('koreksiTagihan')
        );

        return ResponseHelper::success($tindakan, Message::UPDATED);
    }

    public function actionSelesai(string $id): array
    {
        $kunjungan = $this->pemeriksaan->selesai($this->kunjungan($id));

        return ResponseHelper::success($kunjungan, Message::KUNJUNGAN_SELESAI);
    }

    private function kunjungan(string $id): Kunjungan
    {
        $kunjungan = $this->findOr404(Kunjungan::class, $id, ['pemeriksaan', 'poli', 'pasien', 'dokter']);
        $this->pemeriksaan->pastikanDokterPemeriksa($kunjungan, $this->user()->tenaga_medis_id);

        return $kunjungan;
    }
}
