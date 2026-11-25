<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\Form;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Kunjungan;
use app\services\AntreanService;
use app\services\PendaftaranService;
use Yii;
use yii\data\ActiveDataProvider;

class KunjunganController extends ApiController
{
    public const DETAIL_WITH = [
        'pasien', 'poli', 'dokter', 'pemeriksaan', 'diagnosis.icd10', 'tindakan.tindakan',
        'resep.items.obat', 'labOrders.items.jenis', 'labOrders.items.hasil', 'tagihan', 'pendaftar',
    ];
    public const DETAIL_EXPAND = [
        'pasien', 'poli', 'dokter', 'pemeriksaan', 'diagnosis', 'tindakan', 'resep.items', 'labOrders.items', 'tagihan', 'pendaftar',
    ];

    public function __construct(
        $id,
        $module,
        private readonly PendaftaranService $pendaftaran = new PendaftaranService(),
        private readonly AntreanService $antrean = new AntreanService(),
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    protected function permissions(): array
    {
        return [
            'index' => ['lihatAntrean', 'lihatTagihan'],
            'ringkasan' => ['lihatAntrean'],
            'view' => ['lihatAntrean', 'lihatRekamMedis', 'lihatTagihan'],
            'estimasi' => ['lihatAntrean'],
            'create' => 'daftarKunjungan',
            'panggil' => ['daftarKunjungan', 'periksaPasien'],
            'batal' => 'daftarKunjungan',
        ];
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'], 'ringkasan' => ['GET'], 'view' => ['GET'], 'estimasi' => ['GET'],
            'create' => ['POST'], 'panggil' => ['POST'], 'batal' => ['POST'],
        ];
    }

    public function actionIndex(): array
    {
        $query = Kunjungan::find()
            ->alias('k')
            ->joinWith(['pasien p', 'poli po'], false)
            ->with(['pasien', 'poli', 'dokter'])
            ->where(['k.tanggal_kunjungan' => $this->query('tanggal', date('Y-m-d'))])
            ->andFilterWhere(['k.poli_id' => $this->query('poli_id'), 'k.dokter_id' => $this->query('dokter_id')]);

        if ($status = $this->query('status')) {
            $query->andWhere(['k.status' => explode(',', $status)]);
        }

        if (filter_var($this->query('saya'), FILTER_VALIDATE_BOOLEAN)) {
            $query->andWhere(['k.dokter_id' => $this->user()->tenaga_medis_id]);
        }

        if ($q = $this->query('q')) {
            $query->andWhere(['or', ['ilike', 'p.nama', $q], ['ilike', 'p.no_rm', $q], ['ilike', 'k.no_kunjungan', $q]]);
        }

        $provider = new ActiveDataProvider([
            'query' => $query->orderBy($this->sortOrder([
                'antrean' => ['po.kode', 'k.no_antrean'],
                'pasien' => 'p.nama',
                'poli' => 'po.nama',
                'daftar' => 'k.created_at',
                'status' => 'k.status',
            ], ['k.poli_id' => SORT_ASC, 'k.no_antrean' => SORT_ASC])),
            'pagination' => ['pageSizeParam' => 'per_page', 'pageSizeLimit' => [1, 200], 'defaultPageSize' => 50],
        ]);

        return ResponseHelper::paginated($provider, ['pasien', 'poli', 'dokter']);
    }

    public function actionRingkasan(): array
    {
        $rows = (new \yii\db\Query())
            ->select([
                'poli_id' => 'p.id', 'poli' => 'p.nama', 'kode' => 'p.kode',
                'total' => "COUNT(k.id) FILTER (WHERE k.status <> 'batal')",
                'menunggu' => "COUNT(k.id) FILTER (WHERE k.status = 'menunggu')",
                'dipanggil' => "COUNT(k.id) FILTER (WHERE k.status = 'dipanggil')",
                'diperiksa' => "COUNT(k.id) FILTER (WHERE k.status = 'diperiksa')",
                'selesai' => "COUNT(k.id) FILTER (WHERE k.status = 'selesai')",
                'batal' => "COUNT(k.id) FILTER (WHERE k.status = 'batal')",
                'sedang_dilayani' => "MAX(k.no_antrean) FILTER (WHERE k.status IN ('dipanggil','diperiksa'))",
            ])
            ->from(['p' => 'poli'])
            ->leftJoin(['k' => 'kunjungan'], 'k.poli_id = p.id AND k.tanggal_kunjungan = :tanggal', [':tanggal' => $this->query('tanggal', date('Y-m-d'))])
            ->where(['p.is_active' => true])
            ->groupBy(['p.id', 'p.nama', 'p.kode'])
            ->orderBy(['p.nama' => SORT_ASC])
            ->all(Yii::$app->db);

        return ResponseHelper::success($rows, Message::FETCHED);
    }

    public function actionView(string $id): array
    {
        $kunjungan = $this->findOr404(Kunjungan::class, $id, self::DETAIL_WITH);

        return ResponseHelper::success($kunjungan->toArray([], self::DETAIL_EXPAND), Message::FETCHED);
    }

    public function actionCreate(): array
    {
        $kunjungan = $this->pendaftaran->daftar($this->body(), $this->userId());
        $kunjungan = Kunjungan::find()->with(['pasien', 'poli', 'dokter', 'jadwal', 'tagihan'])->where(['id' => $kunjungan->id])->one();

        return ResponseHelper::created(
            $kunjungan->toArray([], ['pasien', 'poli', 'dokter', 'tagihan']) + ['antrean' => $this->antrean->estimasi($kunjungan)],
            Message::KUNJUNGAN_DIDAFTARKAN
        );
    }

    public function actionEstimasi(string $id): array
    {
        $kunjungan = $this->findOr404(Kunjungan::class, $id, ['poli', 'jadwal']);

        return ResponseHelper::success($this->antrean->estimasi($kunjungan), Message::FETCHED);
    }

    public function actionPanggil(string $id): array
    {
        $kunjungan = $this->pendaftaran->panggil($this->findOr404(Kunjungan::class, $id));

        return ResponseHelper::success($kunjungan, Message::KUNJUNGAN_DIPANGGIL);
    }

    public function actionBatal(string $id): array
    {
        $form = Form::validate($this->body(), [
            ['alasan', 'required', 'message' => 'Alasan pembatalan wajib diisi.'],
            ['alasan', 'string', 'max' => 500],
        ]);

        $kunjungan = $this->pendaftaran->batal($this->findOr404(Kunjungan::class, $id), $form->alasan, $this->userId());

        return ResponseHelper::success($kunjungan, Message::KUNJUNGAN_DIBATALKAN);
    }
}
