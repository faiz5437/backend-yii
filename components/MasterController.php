<?php

namespace app\components;

use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;

/**
 * CRUD generik untuk data master: daftar (cari, filter, urut, paginasi), detail, tambah, ubah, hapus.
 */
abstract class MasterController extends ApiController
{
    protected string $modelClass;
    protected array $searchable = ['nama'];
    protected array $filterable = [];
    protected array $with = [];
    protected array $expand = [];
    protected array $defaultOrder = ['nama' => SORT_ASC];
    protected bool $softDelete = false;
    protected string $readPermission = 'lihatMaster';
    protected string $writePermission = 'kelolaMaster';
    protected string $deletePermission = 'kelolaMaster';

    protected function permissions(): array
    {
        return [
            'index' => $this->readPermission,
            'view' => $this->readPermission,
            'create' => $this->writePermission,
            'update' => $this->writePermission,
            'delete' => $this->deletePermission,
        ];
    }

    protected function verbs(): array
    {
        return [
            'index' => ['GET'],
            'view' => ['GET'],
            'create' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'delete' => ['DELETE'],
        ];
    }

    public function actionIndex(): array
    {
        $model = new $this->modelClass();

        $provider = new ActiveDataProvider([
            'query' => $this->buildQuery(),
            'pagination' => ['pageSizeParam' => 'per_page', 'pageSizeLimit' => [1, 200], 'defaultPageSize' => 20],
            'sort' => ['attributes' => $model->attributes(), 'defaultOrder' => $this->defaultOrder],
        ]);

        return ResponseHelper::paginated($provider, $this->expand);
    }

    public function actionView(string $id): array
    {
        return ResponseHelper::success($this->findModel($id)->toArray([], $this->expand), Message::FETCHED);
    }

    public function actionCreate(): array
    {
        $model = new $this->modelClass();
        $model->load($this->body(), '');
        $this->save($model);
        $model->refresh();

        return ResponseHelper::created($model->toArray([], $this->expand));
    }

    public function actionUpdate(string $id): array
    {
        $model = $this->findModel($id);
        $model->load($this->body(), '');
        $this->save($model);

        return ResponseHelper::success($model->toArray([], $this->expand), Message::UPDATED);
    }

    public function actionDelete(string $id): array
    {
        $model = $this->findModel($id);

        if ($this->softDelete) {
            $model->updateAttributes(['deleted_at' => new Expression('NOW()')]);
        } else {
            $model->delete();
        }

        return ResponseHelper::success(null, Message::DELETED);
    }

    protected function buildQuery(): ActiveQuery
    {
        /** @var ActiveQuery $query */
        $query = $this->modelClass::find()->with($this->with);
        $table = $this->modelClass::tableName();

        if ($this->softDelete) {
            $query->andWhere(["{$table}.deleted_at" => null]);
        }

        $keyword = trim((string) $this->query('q', ''));
        if ($keyword !== '' && $this->searchable) {
            $conditions = ['or'];
            foreach ($this->searchable as $column) {
                $conditions[] = ['ilike', "{$table}.{$column}", $keyword];
            }
            $query->andWhere($conditions);
        }

        foreach ($this->filterable as $column) {
            $value = $this->query($column);
            if ($value !== null) {
                $query->andWhere(["{$table}.{$column}" => $value]);
            }
        }

        $aktif = $this->query('aktif');
        if ($aktif !== null && (new $this->modelClass())->hasAttribute('is_active')) {
            $query->andWhere(["{$table}.is_active" => filter_var($aktif, FILTER_VALIDATE_BOOLEAN)]);
        }

        return $query;
    }

    protected function findModel(string $id)
    {
        $model = $this->findOr404($this->modelClass, $id, $this->with);

        if ($this->softDelete && $model->deleted_at !== null) {
            throw ApiException::notFound();
        }

        return $model;
    }

    protected function save($model): void
    {
        if (!$model->save()) {
            throw ApiException::fromModel($model);
        }
    }
}
