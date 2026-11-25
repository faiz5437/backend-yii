<?php

namespace app\models;

use app\behaviors\UuidBehavior;
use yii\db\ActiveRecord;

abstract class BaseModel extends ActiveRecord
{
    public function behaviors(): array
    {
        return ['uuid' => UuidBehavior::class];
    }

    /**
     * Kolom NUMERIC dikembalikan PDO sebagai string, jadi dikonversi ke float saat serialisasi.
     */
    protected static function decimalFields(): array
    {
        return [];
    }

    public function fields(): array
    {
        $fields = parent::fields();

        foreach (static::decimalFields() as $field) {
            $fields[$field] = fn () => $this->$field === null ? null : (float) $this->$field;
        }

        return $fields;
    }

    public static function findForUpdate(string $id): ?static
    {
        return static::findBySql('SELECT * FROM ' . static::tableName() . ' WHERE id = :id FOR UPDATE', [':id' => $id])->one();
    }
}
