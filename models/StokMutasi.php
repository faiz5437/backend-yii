<?php

namespace app\models;

use yii\db\ActiveQuery;

class StokMutasi extends BaseModel
{
    public static function tableName(): string
    {
        return 'stok_mutasi';
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function extraFields(): array
    {
        return ['user' => fn () => $this->user ? ['id' => $this->user->id, 'nama' => $this->user->nama] : null];
    }
}
