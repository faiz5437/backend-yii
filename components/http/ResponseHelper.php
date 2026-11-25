<?php

namespace app\components\http;

use Yii;
use yii\data\DataProviderInterface;
use yii\helpers\ArrayHelper;

final class ResponseHelper
{
    public static function success(mixed $data = null, string $message = Message::SUCCESS, int $code = HttpCode::OK): array
    {
        return self::payload(true, $code, $message, $data);
    }

    public static function created(mixed $data = null, string $message = Message::CREATED): array
    {
        return self::payload(true, HttpCode::CREATED, $message, $data);
    }

    public static function paginated(DataProviderInterface $provider, array $expand = [], string $message = Message::FETCHED): array
    {
        $pagination = $provider->getPagination();
        $items = array_map(
            static fn ($model) => is_object($model) && method_exists($model, 'toArray') ? $model->toArray([], $expand) : $model,
            $provider->getModels()
        );

        return self::payload(true, HttpCode::OK, $message, $items, null, [
            'page' => $pagination ? $pagination->getPage() + 1 : 1,
            'per_page' => $pagination ? $pagination->getPageSize() : count($items),
            'total' => $provider->getTotalCount(),
            'total_pages' => $pagination ? $pagination->getPageCount() : 1,
        ]);
    }

    public static function error(string $message, int $code = HttpCode::BAD_REQUEST, mixed $errors = null): array
    {
        return self::payload(false, $code, $message, null, $errors);
    }

    public static function payload(
        bool $success,
        int $code,
        string $message,
        mixed $data = null,
        mixed $errors = null,
        ?array $pagination = null
    ): array {
        if (Yii::$app->has('response')) {
            Yii::$app->response->statusCode = $code;
        }

        $body = [
            'meta' => [
                'code' => $code,
                'success' => $success,
                'message' => $message,
                'timestamp' => date(DATE_ATOM),
            ],
            'data' => self::normalize($data),
        ];

        if ($pagination !== null) {
            $body['pagination'] = $pagination;
        }

        if ($errors !== null) {
            $body['errors'] = $errors;
        }

        return $body;
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data === null || is_scalar($data)) {
            return $data;
        }

        return ArrayHelper::toArray($data);
    }
}
