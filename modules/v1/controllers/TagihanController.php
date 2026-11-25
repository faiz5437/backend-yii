<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\HttpCode;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Pembayaran;
use app\models\Tagihan;
use app\services\PembayaranService;
use Yii;
use yii\data\ActiveDataProvider;

class TagihanController extends ApiController
{
    private const LIST_WITH = ['kunjungan.pasien', 'kunjungan.poli'];
    private const LIST_EXPAND = ['kunjungan.pasien', 'kunjungan.poli'];
    private const DETAIL_WITH = ['items', 'pembayaran.kasir', 'kunjungan.pasien', 'kunjungan.poli', 'kunjungan.dokter'];
    private const DETAIL_EXPAND = ['items', 'pembayaran.kasir', 'kunjungan.pasien', 'kunjungan.poli', 'kunjungan.dokter'];

    public function __construct($id, $module, private readonly PembayaranService $pembayaran = new PembayaranService(), $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function permissions(): array
    {
        return ['index' => 'lihatTagihan', 'view' => 'lihatTagihan', 'bayar' => 'prosesPembayaran'];
    }

    protected function verbs(): array
    {
        return ['index' => ['GET'], 'view' => ['GET'], 'bayar' => ['POST']];
    }

    public function actionIndex(): array
    {
        $query = Tagihan::find()
            ->alias('t')
            ->joinWith('kunjungan.pasien p', false)
            ->with(self::LIST_WITH)
            ->andFilterWhere(['>=', 'kunjungan.tanggal_kunjungan', $this->query('dari')])
            ->andFilterWhere(['<=', 'kunjungan.tanggal_kunjungan', $this->query('sampai')])
            ->andWhere(['>', 't.total_tagihan', 0]);

        if ($status = $this->query('status')) {
            $query->andWhere(['t.status' => explode(',', $status)]);
        }

        if ($q = $this->query('q')) {
            $query->andWhere(['or', ['ilike', 't.no_invoice', $q], ['ilike', 'p.nama', $q], ['ilike', 'p.no_rm', $q]]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->orderBy($this->sortOrder([
                'no_invoice' => 't.no_invoice',
                'pasien' => 'p.nama',
                'total' => 't.total_tagihan',
                'dibayar' => 't.total_dibayar',
                'sisa' => '(t.total_tagihan - t.total_dibayar)',
                'status' => 't.status',
            ], ['t.created_at' => SORT_DESC])),
            'pagination' => ['pageSizeParam' => 'per_page', 'defaultPageSize' => 20],
        ]);

        return ResponseHelper::paginated($provider, self::LIST_EXPAND);
    }

    public function actionView(string $id): array
    {
        return ResponseHelper::success($this->detail($id), Message::FETCHED);
    }

    public function actionBayar(string $id): array
    {
        $this->findOr404(Tagihan::class, $id);

        [$pembayaran, $replay] = $this->pembayaran->bayar(
            $id,
            Yii::$app->request->headers->get('Idempotency-Key'),
            $this->body(),
            $this->userId()
        );

        $data = ['pembayaran' => $pembayaran, 'tagihan' => $this->detail($id), 'replay' => $replay];

        if ($replay) {
            return ResponseHelper::success($data, Message::PEMBAYARAN_SUDAH_TERCATAT);
        }

        $message = $pembayaran->status === Pembayaran::STATUS_PENDING ? Message::PEMBAYARAN_PENDING : Message::PEMBAYARAN_BERHASIL;

        return ResponseHelper::success($data, $message, HttpCode::CREATED);
    }

    private function detail(string $id): array
    {
        return $this->findOr404(Tagihan::class, $id, self::DETAIL_WITH)->toArray([], self::DETAIL_EXPAND);
    }
}
