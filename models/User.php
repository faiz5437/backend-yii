<?php

namespace app\models;

use Yii;
use yii\db\ActiveQuery;
use yii\web\IdentityInterface;

class User extends BaseModel implements IdentityInterface
{
    public ?string $password = null;
    public array|string|null $roles = null;

    public static function tableName(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            [['username', 'nama'], 'required'],
            [['username', 'nama', 'email'], 'trim'],
            ['username', 'filter', 'filter' => 'strtolower'],
            ['username', 'match', 'pattern' => '/^[a-z0-9._]{3,50}$/', 'message' => 'Username 3-50 karakter: huruf kecil, angka, titik, atau garis bawah.'],
            ['username', 'unique'],
            ['nama', 'string', 'max' => 150],
            ['email', 'email'],
            ['email', 'unique'],
            ['email', 'default', 'value' => null],
            ['password', 'required', 'when' => fn () => $this->isNewRecord],
            ['password', 'string', 'min' => 8],
            ['tenaga_medis_id', 'exist', 'targetClass' => TenagaMedis::class, 'targetAttribute' => 'id'],
            ['tenaga_medis_id', 'unique', 'message' => 'Tenaga medis ini sudah memiliki akun.'],
            ['tenaga_medis_id', 'default', 'value' => null],
            ['is_active', 'boolean'],
            ['roles', 'required', 'when' => fn () => $this->isNewRecord],
            ['roles', 'validateRoles'],
        ];
    }

    public function validateRoles(string $attribute): void
    {
        if ($this->roles === null) {
            return;
        }

        $available = array_keys(Yii::$app->authManager->getRoles());
        foreach ((array) $this->roles as $role) {
            if (!in_array($role, $available, true)) {
                $this->addError($attribute, "Role '{$role}' tidak dikenal.");
            }
        }
    }

    public function beforeSave($insert): bool
    {
        if ($this->password) {
            $this->password_hash = Yii::$app->security->generatePasswordHash($this->password, 10);
        }

        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);

        if ($this->roles !== null) {
            $auth = Yii::$app->authManager;
            $auth->revokeAll($this->id);
            foreach ((array) $this->roles as $role) {
                $auth->assign($auth->getRole($role), $this->id);
            }
        }
    }

    public function validatePassword(string $password): bool
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    public function roleNames(): array
    {
        return array_keys(Yii::$app->authManager->getRolesByUser($this->id));
    }

    public function permissionNames(): array
    {
        return array_keys(Yii::$app->authManager->getPermissionsByUser($this->id));
    }

    public function getTenagaMedis(): ActiveQuery
    {
        return $this->hasOne(TenagaMedis::class, ['id' => 'tenaga_medis_id']);
    }

    public function fields(): array
    {
        $fields = parent::fields();
        unset($fields['password_hash']);

        return array_merge($fields, ['roles' => fn () => $this->roleNames()]);
    }

    public function extraFields(): array
    {
        return ['tenagaMedis'];
    }

    public static function findIdentity($id): ?static
    {
        return static::findOne(['id' => $id, 'is_active' => true]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?static
    {
        $payload = Yii::$app->jwt->decode((string) $token);

        return $payload ? static::findIdentity($payload->sub) : null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAuthKey(): ?string
    {
        return null;
    }

    public function validateAuthKey($authKey): bool
    {
        return false;
    }
}
