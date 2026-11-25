<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\Form;
use app\components\RateLimitFilter;
use app\components\http\HttpCode;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use app\models\Kunjungan;
use app\models\Pasien;
use app\services\AntreanService;
use app\services\PendaftaranService;
use app\services\PengaturanService;
use Ramsey\Uuid\Uuid;
use Yii;

/**
 * Anjungan pendaftaran mandiri: dipakai pasien tanpa login.
 * Pasien lama dikenali dari nomor rekam medis atau NIK, pasien baru dapat mendaftar sendiri.
 * Data yang dikembalikan dibatasi (nama disamarkan, tanpa ID internal).
 */
class AnjunganController extends ApiController
{
    protected array $publicActions = ['info', 'jadwal', 'pasien', 'pasien-baru', 'daftar', 'tiket'];

    public function __construct(
        $id,
        $module,
        private readonly PendaftaranService $pendaftaran = new PendaftaranService(),
        private readonly AntreanService $antrean = new AntreanService(),
        $config = []
    ) {
        parent::__construct($id, $module, $config);
    }

    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'rateLimit' => ['class' => RateLimitFilter::class, 'only' => ['pasien', 'daftar'], 'limit' => Yii::$app->params['anjungan']['rateLimit']],
            'rateLimitPasienBaru' => ['class' => RateLimitFilter::class, 'only' => ['pasien-baru'], 'limit' => Yii::$app->params['anjungan']['rateLimitPasienBaru']],
        ]);
    }

    protected function verbs(): array
    {
        return [
            'info' => ['GET'], 'jadwal' => ['GET'], 'tiket' => ['GET'],
            'pasien' => ['POST'], 'pasien-baru' => ['POST'], 'daftar' => ['POST'],
        ];
    }

    public function actionInfo(): array
    {
        return ResponseHelper::success([
            'aktif' => PengaturanService::get(PengaturanService::ANJUNGAN_AKTIF),
            'tanggal' => date('Y-m-d'),
            'durasi_layanan_menit' => PengaturanService::get(PengaturanService::DURASI_LAYANAN),
        ], Message::FETCHED);
    }

    public function actionJadwal(): array
    {
        $this->pastikanAktif();

        return ResponseHelper::success(
            $this->antrean->jadwalTersedia(date('Y-m-d'), $this->query('poli_id'), true),
            Message::FETCHED
        );
    }

    public function actionPasien(): array
    {
        $this->pastikanAktif();
        $pasien = $this->verifikasiPasien($this->body());

        return ResponseHelper::success($this->ringkasPasien($pasien), Message::FETCHED);
    }

    /**
     * Pendaftaran pasien baru dari anjungan. NIK wajib agar pasien yang sama tidak terdaftar dua kali.
     * Data lain (alamat, alergi, dll.) dilengkapi petugas di loket.
     */
    public function actionPasienBaru(): array
    {
        $this->pastikanAktif();

        $pasien = new Pasien();
        $pasien->load($this->body(), '');
        $pasien->setAttributes(['alergi' => null, 'golongan_darah' => null], false);

        $pasien->validate();
        if (empty($pasien->nik) && !$pasien->hasErrors('nik')) {
            $pasien->addError('nik', 'NIK wajib diisi.');
        }
        if ($pasien->hasErrors()) {
            throw ApiException::fromModel($pasien);
        }

        $pasien->save(false);

        $pasien->refresh();

        return ResponseHelper::created($this->ringkasPasien($pasien), Message::ANJUNGAN_PASIEN_BARU);
    }

    public function actionDaftar(): array
    {
        $this->pastikanAktif();
        $body = $this->body();
        $pasien = $this->verifikasiPasien($body);

        $kunjungan = $this->pendaftaran->daftar([
            'pasien_id' => $pasien->id,
            'jadwal_id' => $body['jadwal_id'] ?? null,
            'tanggal' => date('Y-m-d'),
            'keluhan_awal' => $body['keluhan_awal'] ?? null,
        ], null, Kunjungan::SUMBER_ANJUNGAN);

        return ResponseHelper::created($this->tiket($kunjungan->id), Message::KUNJUNGAN_DIDAFTARKAN);
    }

    public function actionTiket(string $id): array
    {
        return ResponseHelper::success($this->tiket($id), Message::FETCHED);
    }

    private function tiket(string $id): array
    {
        $kunjungan = Uuid::isValid($id)
            ? Kunjungan::find()->with(['pasien', 'poli', 'dokter', 'jadwal'])->where(['id' => $id])->one()
            : null;

        if (!$kunjungan) {
            throw ApiException::notFound();
        }

        return [
            'id' => $kunjungan->id,
            'no_kunjungan' => $kunjungan->no_kunjungan,
            'kode_antrean' => $kunjungan->kodeAntrean(),
            'tanggal_kunjungan' => $kunjungan->tanggal_kunjungan,
            'poli' => $kunjungan->poli->nama,
            'dokter' => $kunjungan->dokter->nama,
            'pasien' => $this->ringkasPasien($kunjungan->pasien),
            'antrean' => $this->antrean->estimasi($kunjungan),
            'dibuat_at' => $kunjungan->created_at,
        ];
    }

    /**
     * Pasien dicari dari nomor rekam medis atau NIK (salah satu).
     */
    private function verifikasiPasien(array $data): Pasien
    {
        $form = Form::validate($data, [
            [['no_rm', 'nik'], 'filter', 'filter' => fn ($v) => strtoupper(trim((string) $v))],
            ['no_rm', 'required', 'when' => fn ($m) => $m->nik === '', 'message' => 'Isi nomor rekam medis atau NIK.'],
            ['no_rm', 'string', 'max' => 20],
            ['nik', 'match', 'pattern' => '/^[0-9]{16}$/', 'message' => 'NIK harus 16 digit angka.'],
        ]);

        $kondisi = $form->no_rm !== '' ? ['no_rm' => $form->no_rm] : ['nik' => $form->nik];
        $pasien = Pasien::findOne($kondisi + ['deleted_at' => null]);

        return $pasien ?? throw new ApiException(HttpCode::NOT_FOUND, Message::ANJUNGAN_PASIEN_TIDAK_DITEMUKAN);
    }

    /**
     * Nama disamarkan sebagian karena layar anjungan bisa terlihat orang lain.
     */
    private function ringkasPasien(Pasien $pasien): array
    {
        $kata = preg_split('/\s+/', trim($pasien->nama));
        $nama = implode(' ', array_map(
            fn ($k, $i) => $i === 0 ? $k : mb_substr($k, 0, 1) . '.',
            $kata,
            array_keys($kata)
        ));

        return [
            'no_rm' => $pasien->no_rm,
            'nama' => $nama,
            'jenis_kelamin' => $pasien->jenis_kelamin,
        ];
    }

    private function pastikanAktif(): void
    {
        if (!PengaturanService::get(PengaturanService::ANJUNGAN_AKTIF)) {
            throw new ApiException(HttpCode::SERVICE_UNAVAILABLE, Message::ANJUNGAN_NONAKTIF);
        }
    }
}
