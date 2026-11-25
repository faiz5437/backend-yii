<?php

namespace app\modules\v1\controllers;

use app\components\Form;
use app\components\MasterController;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\models\Obat;
use app\models\StokMutasi;
use app\services\FarmasiService;
use yii\data\ActiveDataProvider;
use yii\db\ActiveQuery;
use yii\db\Expression;

class ObatController extends MasterController
{
    protected string $modelClass = Obat::class;
    protected array $searchable = ['kode', 'nama'];
    protected array $filterable = ['bentuk'];

    protected function permissions(): array
    {
        return array_merge(parent::permissions(), [
            'stok' => 'kelolaStok',
            'mutasi' => ['kelolaStok', 'prosesResep'],
        ]);
    }

    protected function verbs(): array
    {
        return array_merge(parent::verbs(), ['stok' => ['POST'], 'mutasi' => ['GET']]);
    }

    protected function buildQuery(): ActiveQuery
    {
        $query = parent::buildQuery();

        if (filter_var($this->query('menipis'), FILTER_VALIDATE_BOOLEAN)) {
            $query->andWhere(new Expression('stok <= stok_minimum'));
        }

        return $query;
    }

    public function actionStok(string $id): array
    {
        $this->findModel($id);

        $form = Form::validate($this->body(), [
            [['tipe', 'qty'], 'required'],
            ['tipe', 'in', 'range' => ['masuk', 'penyesuaian']],
            ['qty', 'integer', 'min' => 1],
            ['keterangan', 'string', 'max' => 500],
        ]);

        $obat = (new FarmasiService())->sesuaikanStok($id, $form->tipe, (int) $form->qty, $form->keterangan, $this->userId());

        return ResponseHelper::success($obat, Message::STOK_DISESUAIKAN);
    }

    public function actionMutasi(string $id): array
    {
        $this->findModel($id);

        $order = $this->sortOrder(
            ['created_at' => 'created_at', 'tipe' => 'tipe', 'qty' => 'qty', 'saldo' => 'stok_sesudah'],
            ['created_at' => SORT_DESC, 'id' => SORT_DESC]
        );

        $provider = new ActiveDataProvider([
            'query' => StokMutasi::find()->with('user')->where(['obat_id' => $id])->orderBy($order),
            'pagination' => ['pageSizeParam' => 'per_page', 'defaultPageSize' => 20],
        ]);

        return ResponseHelper::paginated($provider, ['user']);
    }
}
