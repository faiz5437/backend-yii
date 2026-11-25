<?php

namespace app\models;

use yii\db\ActiveQuery;

class RefreshToken extends BaseModel
{
    public static function tableName(): string
    {
        return 'refresh_token';
    }

    public function getUser(): ActiveQuery
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function isUsable(): bool
    {
        return $this->revoked_at === null && strtotime($this->expires_at) > time();
    }
}
