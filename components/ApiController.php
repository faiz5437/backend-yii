<?php

namespace app\components;

use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\User;
use Ramsey\Uuid\Uuid;
use Yii;
use yii\db\ActiveRecord;
use yii\filters\auth\HttpBearerAuth;
use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\rest\Controller;
use yii\web\Response;

abstract class ApiController extends Controller
{
    public $enableCsrfValidation = false;

    /** Action yang dapat diakses tanpa token. */
    protected array $publicActions = [];

    /**
     * Pemetaan action => permission RBAC. Key '*' berlaku untuk action yang tidak disebut.
     * Jika salah satu permission dimiliki user, akses diizinkan.
     */
    protected function permissions(): array
    {
        return [];
    }

    public function behaviors(): array
    {
        return [
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => Yii::$app->params['corsOrigins'],
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'],
                    'Access-Control-Allow-Credentials' => true,
                    'Access-Control-Max-Age' => 3600,
                ],
            ],
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
            'verbFilter' => [
                'class' => \yii\filters\VerbFilter::class,
                'actions' => $this->verbs(),
            ],
            'authenticator' => [
                'class' => HttpBearerAuth::class,
                'except' => array_merge(['options'], $this->publicActions),
            ],
        ];
    }

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (!in_array($action->id, $this->publicActions, true)) {
            $this->authorize($action->id);
            $this->bindAuditUser();
        }

        return true;
    }

    protected function authorize(string $actionId): void
    {
        $map = $this->permissions();
        $required = $map[$actionId] ?? $map['*'] ?? null;

        if ($required === null) {
            return;
        }

        foreach ((array) $required as $permission) {
            if (Yii::$app->user->can($permission)) {
                return;
            }
        }

        throw ApiException::forbidden();
    }

    /**
     * Trigger audit di database membaca app.user_id untuk mencatat pelaku perubahan.
     */
    protected function bindAuditUser(): void
    {
        Yii::$app->db->createCommand("SELECT set_config('app.user_id', :id, false)", [':id' => (string) $this->userId()])->execute();
    }

    protected function user(): User
    {
        return Yii::$app->user->identity;
    }

    protected function userId(): ?string
    {
        return Yii::$app->user->id;
    }

    protected function can(string $permission): bool
    {
        return Yii::$app->user->can($permission);
    }

    protected function body(): array
    {
        return Yii::$app->request->getBodyParams();
    }

    protected function query(string $name, mixed $default = null): mixed
    {
        $value = Yii::$app->request->get($name, $default);

        return $value === '' ? $default : $value;
    }

    /**
     * Urutan data dari parameter `sort` (mis. `nama` atau `-nama` untuk menurun).
     * Hanya key yang ada di $allowed yang diterima, sehingga kolom SQL tidak bisa disisipkan dari luar.
     * $default tetap dipakai sebagai urutan berikutnya agar hasil stabil antar halaman.
     *
     * @param array<string, string|string[]> $allowed key sort => kolom SQL
     */
    protected function sortOrder(array $allowed, array $default): array
    {
        $sort = (string) $this->query('sort', '');
        $key = ltrim($sort, '-');

        if (!isset($allowed[$key])) {
            return $default;
        }

        $direction = str_starts_with($sort, '-') ? SORT_DESC : SORT_ASC;
        $order = array_fill_keys((array) $allowed[$key], $direction);

        return $order + $default;
    }

    /**
     * @template T of ActiveRecord
     * @param class-string<T> $class
     * @return T
     */
    protected function findOr404(string $class, string $id, array $with = []): ActiveRecord
    {
        $model = Uuid::isValid($id) ? $class::find()->with($with)->where(['id' => $id])->one() : null;

        return $model ?? throw ApiException::notFound(Message::NOT_FOUND);
    }
}
