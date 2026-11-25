<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Pembayaran;
use app\services\PembayaranService;
use yii\data\ActiveDataProvider;

class PembayaranController extends ApiController
{
    private const WITH = ['kasir', 'tagihan.items', 'tagihan.kunjungan.pasien', 'tagihan.kunjungan.poli'];
    private const EXPAND = ['kasir', 'tagihan.items', 'tagihan.kunjungan.pasien', 'tagihan.kunjungan.poli'];

    protected function permissions(): array
    {
        return ['index' => 'lihatTagihan', 'view' => 'lihatTagihan', 'konfirmasi' => 'prosesPembayaran'];
    }

    protected function verbs(): array
    {
        return ['index' => ['GET'], 'view' => ['GET'], 'konfirmasi' => ['POST']];
    }

    public function actionIndex(): array
    {
        $query = Pembayaran::find()
            ->alias('b')
            ->joinWith('tagihan.kunjungan.pasien p', false)
            ->with(['kasir', 'tagihan.kunjungan.pasien'])
            ->andFilterWhere(['b.tagihan_id' => $this->query('tagihan_id'), 'b.metode' => $this->query('metode'), 'b.status' => $this->query('status')])
            ->andFilterWhere(['>=', 'DATE(b.created_at)', $this->query('dari')])
            ->andFilterWhere(['<=', 'DATE(b.created_at)', $this->query('sampai')]);

        if ($q = $this->query('q')) {
            $query->andWhere(['or', ['ilike', 'b.no_kwitansi', $q], ['ilike', 'p.nama', $q], ['ilike', 'p.no_rm', $q]]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->orderBy($this->sortOrder([
                'no' => 'b.no_kwitansi',
                'pasien' => 'p.nama',
                'metode' => 'b.metode',
                'jumlah' => 'b.jumlah',
                'status' => 'b.status',
            ], ['b.created_at' => SORT_DESC])),
            'pagination' => ['pageSizeParam' => 'per_page', 'defaultPageSize' => 20],
        ]);

        return ResponseHelper::paginated($provider, ['kasir', 'tagihan.kunjungan.pasien']);
    }

    public function actionView(string $id): array
    {
        return ResponseHelper::success($this->findOr404(Pembayaran::class, $id, self::WITH)->toArray([], self::EXPAND), Message::FETCHED);
    }

    public function actionKonfirmasi(string $id): array
    {
        $this->findOr404(Pembayaran::class, $id);
        [, $replay] = (new PembayaranService())->konfirmasi($id);

        $data = $this->findOr404(Pembayaran::class, $id, self::WITH)->toArray([], self::EXPAND);

        return ResponseHelper::success($data, $replay ? Message::PEMBAYARAN_SUDAH_TERCATAT : Message::PEMBAYARAN_DIKONFIRMASI);
    }
}
