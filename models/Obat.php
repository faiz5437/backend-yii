<?php

namespace app\models;

use app\services\FarmasiService;
use Yii;

class Obat extends BaseModel
{
    /** Stok awal hanya dipakai saat obat baru dibuat dan dicatat sebagai mutasi masuk. */
    public ?int $stok_awal = null;

    public static function tableName(): string
    {
        return 'obat';
    }

    protected static function decimalFields(): array
    {
        return ['harga_jual'];
    }

    public function rules(): array
    {
        return [
            [['kode', 'nama', 'satuan', 'harga_jual'], 'required'],
            [['kode', 'nama', 'bentuk', 'satuan'], 'trim'],
            ['kode', 'filter', 'filter' => 'strtoupper'],
            ['kode', 'string', 'max' => 20],
            ['kode', 'unique'],
            ['nama', 'string', 'max' => 150],
            ['bentuk', 'string', 'max' => 30],
            ['satuan', 'string', 'max' => 20],
            ['harga_jual', 'number', 'min' => 0],
            ['stok_minimum', 'integer', 'min' => 0],
            ['stok_awal', 'integer', 'min' => 0, 'on' => self::SCENARIO_DEFAULT, 'when' => fn () => $this->isNewRecord],
            ['is_active', 'boolean'],
        ];
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        if ($insert && $this->stok_awal > 0) {
            (new FarmasiService())->sesuaikanStok($this->id, 'masuk', $this->stok_awal, 'Stok awal', Yii::$app->has('user') ? Yii::$app->user->id : null);
        }
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            'stok_menipis' => fn () => $this->stok <= $this->stok_minimum,
        ]);
    }
}
