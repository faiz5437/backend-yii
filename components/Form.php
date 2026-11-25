<?php

namespace app\components;

use app\exceptions\ApiException;
use yii\base\DynamicModel;

/**
 * Validasi input non-model. Hanya atribut yang disebut di rules yang diambil dari data,
 * dan atribut yang tidak dikirim tetap terdefinisi sebagai null.
 */
final class Form
{
    public static function make(array $data, array $rules): DynamicModel
    {
        $attributes = [];
        foreach ($rules as $rule) {
            foreach ((array) $rule[0] as $name) {
                $attributes[$name] = $data[$name] ?? null;
            }
        }

        return DynamicModel::validateData($attributes, $rules);
    }

    public static function validate(array $data, array $rules): DynamicModel
    {
        $form = self::make($data, $rules);

        if ($form->hasErrors()) {
            throw ApiException::fromModel($form);
        }

        return $form;
    }
}
