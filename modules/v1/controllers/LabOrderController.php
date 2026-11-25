<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Kunjungan;
use app\models\LabOrder;
use app\services\LabService;
use app\services\PemeriksaanService;
use yii\data\ActiveDataProvider;

class LabOrderController extends ApiController
{
    private const WITH = ['items.jenis', 'items.hasil', 'kunjungan.pasien', 'kunjungan.poli', 'dokter'];
    private const EXPAND = ['items', 'kunjungan.pasien', 'kunjungan.poli', 'dokter'];

    public function __construct($id, $module, private readonly LabService $lab = new LabService(), $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function permissions(): array
    {
        return [
            'create' => 'periksaPasien',
            'index' => ['prosesLab', 'periksaPasien'],
            'view' => ['prosesLab', 'periksaPasien'],
            'proses' => 'prosesLab',
            'hasil' => 'prosesLab',
        ];
    }

    protected function verbs(): array
    {
        return ['create' => ['POST'], 'index' => ['GET'], 'view' => ['GET'], 'proses' => ['POST'], 'hasil' => ['POST']];
    }

    public function actionCreate(string $id): array
    {
        $kunjungan = $this->findOr404(Kunjungan::class, $id, ['pasien', 'dokter']);
        (new PemeriksaanService())->pastikanDokterPemeriksa($kunjungan, $this->user()->tenaga_medis_id);

        $order = $this->lab->buatOrder($kunjungan, $this->body());

        return ResponseHelper::created($this->detail($order->id), Message::LAB_ORDER_DIBUAT);
    }

    public function actionIndex(): array
    {
        $query = LabOrder::find()
            ->alias('l')
            ->joinWith('kunjungan.pasien p', false)
            ->with(self::WITH)
            ->andFilterWhere(['l.kunjungan_id' => $this->query('kunjungan_id')]);

        if ($status = $this->query('status')) {
            $query->andWhere(['l.status' => explode(',', $status)]);
        }

        if ($tanggal = $this->query('tanggal')) {
            $query->andWhere(['DATE(l.created_at)' => $tanggal]);
        }

        if ($q = $this->query('q')) {
            $query->andWhere(['or', ['ilike', 'l.no_order', $q], ['ilike', 'p.nama', $q], ['ilike', 'p.no_rm', $q]]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->orderBy($this->sortOrder([
                'no_order' => 'l.no_order',
                'pasien' => 'p.nama',
                'status' => 'l.status',
            ], ['l.created_at' => SORT_ASC])),
            'pagination' => ['pageSizeParam' => 'per_page', 'defaultPageSize' => 20],
        ]);

        return ResponseHelper::paginated($provider, self::EXPAND);
    }

    public function actionView(string $id): array
    {
        return ResponseHelper::success($this->detail($id), Message::FETCHED);
    }

    public function actionProses(string $id): array
    {
        $this->findOr404(LabOrder::class, $id);
        $this->lab->proses($id);

        return ResponseHelper::success($this->detail($id), Message::LAB_ORDER_DIPROSES);
    }

    public function actionHasil(string $id): array
    {
        $this->findOr404(LabOrder::class, $id);
        $this->lab->simpanHasil($id, $this->body()['items'] ?? [], $this->userId());

        return ResponseHelper::success($this->detail($id), Message::LAB_HASIL_DISIMPAN);
    }

    private function detail(string $id): array
    {
        return $this->findOr404(LabOrder::class, $id, self::WITH)->toArray([], self::EXPAND);
    }
}
