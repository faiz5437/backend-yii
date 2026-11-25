<?php

namespace app\services;

use app\components\Form;
use app\models\Pengaturan;
use Yii;
use yii\db\Expression;

/**
 * Pengaturan sistem yang dapat diubah admin tanpa deploy ulang.
 * Setiap kunci punya tipe dan nilai bawaan, sehingga nilai yang belum tersimpan tetap terbaca.
 */
final class PengaturanService
{
    public const DURASI_LAYANAN = 'durasi_layanan_menit';
    public const ANJUNGAN_AKTIF = 'anjungan_aktif';

    private const DEFINISI = [
        self::DURASI_LAYANAN => ['tipe' => 'int', 'bawaan' => 30],
        self::ANJUNGAN_AKTIF => ['tipe' => 'bool', 'bawaan' => true],
    ];

    private static ?array $cache = null;

    public static function get(string $kunci): mixed
    {
        return self::all()[$kunci] ?? self::DEFINISI[$kunci]['bawaan'] ?? null;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            $tersimpan = Pengaturan::find()->select(['nilai', 'kunci'])->indexBy('kunci')->column();

            self::$cache = [];
            foreach (self::DEFINISI as $kunci => $definisi) {
                self::$cache[$kunci] = isset($tersimpan[$kunci])
                    ? self::cast($tersimpan[$kunci], $definisi['tipe'])
                    : $definisi['bawaan'];
            }
        }

        return self::$cache;
    }

    public static function update(array $data, ?string $userId): array
    {
        $form = Form::validate($data, [
            [[self::DURASI_LAYANAN, self::ANJUNGAN_AKTIF], 'required'],
            [self::DURASI_LAYANAN, 'integer', 'min' => 1, 'max' => 240],
            [self::ANJUNGAN_AKTIF, 'boolean'],
        ]);

        Yii::$app->db->transaction(function () use ($form, $userId) {
            foreach (array_keys(self::DEFINISI) as $kunci) {
                $nilai = self::DEFINISI[$kunci]['tipe'] === 'bool'
                    ? (filter_var($form->$kunci, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false')
                    : (string) (int) $form->$kunci;

                Yii::$app->db->createCommand()->upsert(Pengaturan::tableName(), [
                    'kunci' => $kunci,
                    'nilai' => $nilai,
                    'updated_by' => $userId,
                    'updated_at' => new Expression('NOW()'),
                ])->execute();
            }
        });

        self::$cache = null;

        return self::all();
    }

    private static function cast(string $nilai, string $tipe): mixed
    {
        return match ($tipe) {
            'int' => (int) $nilai,
            'bool' => filter_var($nilai, FILTER_VALIDATE_BOOLEAN),
            default => $nilai,
        };
    }
}
