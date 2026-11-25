<?php

namespace app\modules\v1\controllers;

use app\components\MasterController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use app\models\User;
use app\services\AuthService;
use Yii;

class UserController extends MasterController
{
    protected string $modelClass = User::class;
    protected array $searchable = ['username', 'nama', 'email'];
    protected array $with = ['tenagaMedis'];
    protected array $expand = ['tenagaMedis'];
    protected string $readPermission = 'kelolaUser';
    protected string $writePermission = 'kelolaUser';
    protected string $deletePermission = 'kelolaUser';

    protected function permissions(): array
    {
        return array_merge(parent::permissions(), ['roles' => 'kelolaUser']);
    }

    protected function verbs(): array
    {
        return array_merge(parent::verbs(), ['roles' => ['GET']]);
    }

    public function actionDelete(string $id): array
    {
        if ($id === $this->userId()) {
            throw ApiException::conflict(Message::USER_TIDAK_BISA_HAPUS_DIRI);
        }

        $user = $this->findModel($id);
        $user->updateAttributes(['is_active' => false]);
        (new AuthService())->revokeAllForUser($user->id);

        return ResponseHelper::success(null, Message::DEACTIVATED);
    }

    public function actionRoles(): array
    {
        $roles = array_map(
            fn ($role) => ['name' => $role->name, 'description' => $role->description],
            array_values(Yii::$app->authManager->getRoles())
        );

        return ResponseHelper::success($roles, Message::FETCHED);
    }
}
