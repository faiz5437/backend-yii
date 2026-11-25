<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use app\models\Kunjungan;
use app\models\Pasien;
use yii\data\ActiveDataProvider;

class PasienController extends MasterController
{
    public const RIWAYAT_WITH = [
        'poli', 'dokter', 'pemeriksaan', 'diagnosis.icd10', 'tindakan.tindakan',
        'resep.items.obat', 'labOrders.items.jenis', 'labOrders.items.hasil', 'tagihan',
    ];
    public const RIWAYAT_EXPAND = ['poli', 'dokter', 'pemeriksaan', 'diagnosis', 'tindakan', 'resep.items', 'labOrders.items', 'tagihan'];

    protected string $modelClass = Pasien::class;
    protected array $searchable = ['no_rm', 'nik', 'nama', 'no_hp'];
    protected array $filterable = ['jenis_kelamin'];
    protected array $defaultOrder = ['no_rm' => SORT_DESC];
    protected bool $softDelete = true;
    protected string $readPermission = 'lihatPasien';
    protected string $writePermission = 'kelolaPasien';

    protected function permissions(): array
    {
        return array_merge(parent::permissions(), ['riwayat' => 'lihatRekamMedis', 'no-rm' => 'lihatPasien']);
    }

    protected function verbs(): array
    {
        return array_merge(parent::verbs(), ['riwayat' => ['GET'], 'no-rm' => ['GET']]);
    }

    /**
     * Pencarian tepat berdasarkan nomor rekam medis, dipakai saat kartu pasien dipindai.
     */
    public function actionNoRm(string $noRm): array
    {
        $pasien = Pasien::findOne(['no_rm' => strtoupper(trim($noRm)), 'deleted_at' => null]);

        return ResponseHelper::success($pasien ?? throw ApiException::notFound('Pasien dengan nomor rekam medis tersebut tidak ditemukan'), Message::FETCHED);
    }

    public function actionRiwayat(string $id): array
    {
        $pasien = $this->findModel($id);

        $provider = new ActiveDataProvider([
            'query' => Kunjungan::find()
                ->with(self::RIWAYAT_WITH)
                ->where(['pasien_id' => $pasien->id])
                ->andWhere(['<>', 'status', Kunjungan::STATUS_BATAL])
                ->orderBy(['tanggal_kunjungan' => SORT_DESC, 'created_at' => SORT_DESC]),
            'pagination' => ['pageSizeParam' => 'per_page', 'defaultPageSize' => 10],
        ]);

        return ResponseHelper::paginated($provider, self::RIWAYAT_EXPAND);
    }
}
