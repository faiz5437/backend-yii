<?php

namespace app\services;

use app\components\Form;
use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\HasilLab;
use app\models\JenisPemeriksaanLab;
use app\models\Kunjungan;
use app\models\LabOrder;
use app\models\LabOrderItem;
use app\models\Outbox;
use app\models\TagihanItem;
use Yii;
use yii\db\Expression;

final class LabService
{
    public function __construct(private readonly BillingService $billing = new BillingService())
    {
    }

    /**
     * D5: order lab dan event outbox ditulis dalam satu transaksi.
     * Worker Node.js mengirim event ke sistem laboratorium dan mencoba ulang bila gagal,
     * jadi order tidak hilang walaupun sistem laboratorium sedang tidak dapat diakses.
     */
    public function buatOrder(Kunjungan $kunjungan, array $data): LabOrder
    {
        $form = Form::validate($data, [
            ['jenis_ids', 'required', 'message' => 'Pilih minimal satu jenis pemeriksaan.'],
            ['jenis_ids', 'each', 'rule' => ['match', 'pattern' => '/^[0-9a-f-]{36}$/i']],
            ['catatan_klinis', 'string', 'max' => 1000],
        ]);

        $jenisList = JenisPemeriksaanLab::find()->where(['id' => array_unique($form->jenis_ids), 'is_active' => true])->all();
        if (count($jenisList) !== count(array_unique($form->jenis_ids))) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, ['jenis_ids' => ['Sebagian jenis pemeriksaan tidak ditemukan.']]);
        }

        return Yii::$app->db->transaction(function () use ($kunjungan, $form, $jenisList) {
            $order = new LabOrder([
                'no_order' => NomorService::noLabOrder(),
                'kunjungan_id' => $kunjungan->id,
                'dokter_id' => $kunjungan->dokter_id,
                'catatan_klinis' => $form->catatan_klinis,
            ]);
            $order->save(false);

            foreach ($jenisList as $jenis) {
                $item = new LabOrderItem([
                    'lab_order_id' => $order->id,
                    'jenis_pemeriksaan_id' => $jenis->id,
                    'tarif' => $jenis->tarif,
                ]);
                $item->save(false);

                $this->billing->tambahItem($kunjungan->id, TagihanItem::SUMBER_LAB, $item->id, 'Lab: ' . $jenis->nama, 1, (float) $jenis->tarif);
            }

            $pasien = $kunjungan->pasien;
            Outbox::publish('lab_order.created', 'lab_order', $order->id, [
                'no_order' => $order->no_order,
                'tanggal' => date(DATE_ATOM),
                'catatan_klinis' => $order->catatan_klinis,
                'pasien' => [
                    'no_rm' => $pasien->no_rm,
                    'nama' => $pasien->nama,
                    'jenis_kelamin' => $pasien->jenis_kelamin,
                    'tgl_lahir' => $pasien->tgl_lahir,
                ],
                'dokter' => $kunjungan->dokter->nama,
                'pemeriksaan' => array_map(fn ($j) => ['kode' => $j->kode, 'nama' => $j->nama], $jenisList),
            ]);

            $order->refresh();

            return $order;
        });
    }

    public function proses(string $orderId): void
    {
        $updated = LabOrder::updateAll(['status' => LabOrder::STATUS_DIPROSES], ['id' => $orderId, 'status' => LabOrder::STATUS_DIMINTA]);

        if ($updated === 0) {
            throw ApiException::conflict(Message::LAB_ORDER_STATUS_INVALID);
        }
    }

    /**
     * @param array $items [{lab_order_item_id, nilai, flag?, catatan?, external_result_id?}]
     */
    public function simpanHasil(string $orderId, array $items, ?string $userId, string $sumber = 'manual'): array
    {
        if (count($items) === 0) {
            throw ApiException::unprocessable(Message::VALIDATION_FAILED, ['items' => ['Minimal satu hasil harus diisi.']]);
        }

        return Yii::$app->db->transaction(function () use ($orderId, $items, $userId, $sumber) {
            $order = LabOrder::findForUpdate($orderId) ?? throw ApiException::notFound();

            if (in_array($order->status, [LabOrder::STATUS_BATAL, LabOrder::STATUS_SELESAI], true) && $sumber === 'integrasi') {
                return ['diterima' => 0, 'dilewati' => count($items)];
            }

            if ($order->status === LabOrder::STATUS_BATAL) {
                throw ApiException::conflict(Message::LAB_ORDER_STATUS_INVALID);
            }

            $orderItems = LabOrderItem::find()->where(['lab_order_id' => $order->id])->indexBy('id')->all();
            $errors = [];
            $diterima = 0;
            $dilewati = 0;

            foreach ($items as $i => $data) {
                $orderItem = $orderItems[$data['lab_order_item_id'] ?? ''] ?? null;
                if (!$orderItem) {
                    $errors["items.{$i}"] = ['lab_order_item_id' => 'Item pemeriksaan tidak ditemukan pada order ini.'];
                    continue;
                }

                $externalId = $data['external_result_id'] ?? null;
                if ($externalId && HasilLab::find()->where(['external_result_id' => $externalId])->exists()) {
                    $dilewati++;
                    continue;
                }

                $hasil = $orderItem->hasil ?? new HasilLab(['lab_order_item_id' => $orderItem->id]);
                $hasil->load($data, '');
                $hasil->satuan = $orderItem->jenis->satuan;
                $hasil->nilai_rujukan = $orderItem->jenis->nilai_rujukan;
                $hasil->sumber = $sumber;
                $hasil->external_result_id = $externalId ?? $hasil->external_result_id;
                $hasil->diinput_oleh = $userId;

                if (!$hasil->save()) {
                    $errors["items.{$i}"] = $hasil->getFirstErrors();
                    continue;
                }

                $orderItem->updateAttributes(['status' => 'selesai']);
                $diterima++;
            }

            if ($errors) {
                throw ApiException::unprocessable(Message::VALIDATION_FAILED, $errors);
            }

            $belumSelesai = LabOrderItem::find()->where(['lab_order_id' => $order->id, 'status' => 'menunggu'])->exists();
            $order->updateAttributes($belumSelesai
                ? ['status' => LabOrder::STATUS_DIPROSES]
                : ['status' => LabOrder::STATUS_SELESAI, 'selesai_at' => new Expression('COALESCE(selesai_at, NOW())')]);

            return ['diterima' => $diterima, 'dilewati' => $dilewati, 'status_order' => LabOrder::findOne($order->id)->status];
        });
    }

    /**
     * Hasil dari sistem laboratorium eksternal (diteruskan oleh service Node.js).
     * Idempotent: hasil dengan external_result_id yang sama hanya disimpan sekali.
     */
    public function simpanHasilIntegrasi(array $payload): array
    {
        $form = Form::validate($payload, [
            [['no_order', 'hasil'], 'required'],
            ['no_order', 'string', 'max' => 30],
        ]);

        $order = LabOrder::find()->with('items.jenis')->where(['no_order' => $form->no_order])->one()
            ?? throw ApiException::notFound('Order laboratorium tidak ditemukan');

        $itemByKode = [];
        foreach ($order->items as $item) {
            $itemByKode[$item->jenis->kode] = $item->id;
        }

        $items = [];
        foreach ((array) $form->hasil as $i => $row) {
            $kode = strtoupper($row['kode'] ?? '');
            if (!isset($itemByKode[$kode])) {
                throw ApiException::unprocessable(Message::VALIDATION_FAILED, ["hasil.{$i}" => ["Kode pemeriksaan {$kode} tidak ada pada order ini."]]);
            }

            $items[] = [
                'lab_order_item_id' => $itemByKode[$kode],
                'nilai' => $row['nilai'] ?? null,
                'flag' => $row['flag'] ?? null,
                'catatan' => $row['catatan'] ?? null,
                'external_result_id' => $row['external_result_id'] ?? null,
            ];
        }

        return $this->simpanHasil($order->id, $items, null, 'integrasi');
    }
}
