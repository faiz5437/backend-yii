<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Kunjungan;
use app\models\Resep;
use app\services\FarmasiService;
use app\services\PemeriksaanService;
use yii\data\ActiveDataProvider;

class ResepController extends ApiController
{
    private const WITH = ['items.obat', 'kunjungan.pasien', 'kunjungan.poli', 'dokter'];
    private const EXPAND = ['items', 'kunjungan.pasien', 'kunjungan.poli', 'dokter'];

    public function __construct($id, $module, private readonly FarmasiService $farmasi = new FarmasiService(), $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function permissions(): array
    {
        return [
            'create' => 'periksaPasien',
            'index' => ['prosesResep', 'periksaPasien'],
            'view' => ['prosesResep', 'periksaPasien'],
            'proses' => 'prosesResep',
            'serahkan' => 'prosesResep',
            'batal' => 'prosesResep',
        ];
    }

    protected function verbs(): array
    {
        return [
            'create' => ['POST'], 'index' => ['GET'], 'view' => ['GET'],
            'proses' => ['POST'], 'serahkan' => ['POST'], 'batal' => ['POST'],
        ];
    }

    public function actionCreate(string $id): array
    {
        $kunjungan = $this->findOr404(Kunjungan::class, $id);
        (new PemeriksaanService())->pastikanDokterPemeriksa($kunjungan, $this->user()->tenaga_medis_id);

        $resep = $this->farmasi->buatResep($kunjungan, $this->body());

        return ResponseHelper::created($this->detail($resep->id), Message::RESEP_DIBUAT);
    }

    public function actionIndex(): array
    {
        $query = Resep::find()
            ->alias('r')
            ->joinWith('kunjungan.pasien p', false)
            ->with(self::WITH)
            ->andFilterWhere(['r.kunjungan_id' => $this->query('kunjungan_id')]);

        if ($status = $this->query('status')) {
            $query->andWhere(['r.status' => explode(',', $status)]);
        }

        if ($tanggal = $this->query('tanggal')) {
            $query->andWhere(['DATE(r.created_at)' => $tanggal]);
        }

        if ($q = $this->query('q')) {
            $query->andWhere(['or', ['ilike', 'r.no_resep', $q], ['ilike', 'p.nama', $q], ['ilike', 'p.no_rm', $q]]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->orderBy($this->sortOrder([
                'no_resep' => 'r.no_resep',
                'pasien' => 'p.nama',
                'status' => 'r.status',
            ], ['r.created_at' => SORT_ASC])),
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
        $this->findOr404(Resep::class, $id);
        $this->farmasi->proses($id, $this->userId());

        return ResponseHelper::success($this->detail($id), Message::RESEP_DIPROSES);
    }

    public function actionSerahkan(string $id): array
    {
        $this->findOr404(Resep::class, $id);
        $this->farmasi->serahkan($id, $this->userId());

        return ResponseHelper::success($this->detail($id), Message::RESEP_DISERAHKAN);
    }

    public function actionBatal(string $id): array
    {
        $this->findOr404(Resep::class, $id);
        $this->farmasi->batal($id);

        return ResponseHelper::success($this->detail($id), Message::RESEP_DIBATALKAN);
    }

    private function detail(string $id): array
    {
        return $this->findOr404(Resep::class, $id, self::WITH)->toArray([], self::EXPAND);
    }
}
