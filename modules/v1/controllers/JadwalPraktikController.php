<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\JadwalPraktik;
use app\services\AntreanService;

class JadwalPraktikController extends MasterController
{
    protected string $modelClass = JadwalPraktik::class;
    protected array $searchable = [];
    protected array $filterable = ['poli_id', 'dokter_id', 'hari'];
    protected array $with = ['dokter', 'poli'];
    protected array $expand = ['dokter', 'poli'];
    protected array $defaultOrder = ['hari' => SORT_ASC, 'jam_mulai' => SORT_ASC];

    protected function permissions(): array
    {
        return array_merge(parent::permissions(), ['tersedia' => ['lihatMaster']]);
    }

    protected function verbs(): array
    {
        return array_merge(parent::verbs(), ['tersedia' => ['GET']]);
    }

    /**
     * Jadwal yang aktif pada tanggal tertentu beserta sisa kuota dan estimasi antrean.
     */
    public function actionTersedia(): array
    {
        $data = (new AntreanService())->jadwalTersedia($this->query('tanggal', date('Y-m-d')), $this->query('poli_id'));

        return ResponseHelper::success($data, Message::FETCHED);
    }
}
