<?php

namespace app\components;

use app\components\http\HttpCode;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use Yii;
use yii\db\IntegrityException;
use yii\db\StaleObjectException;
use yii\web\HttpException;
use yii\web\Response;

class ErrorHandler extends \yii\web\ErrorHandler
{
    private const PG_UNIQUE_VIOLATION = '23505';
    private const PG_FOREIGN_KEY_VIOLATION = '23503';
    private const PG_CHECK_VIOLATION = '23514';
    private const PG_EXCLUSION_VIOLATION = '23P01';

    private const DEFAULT_MESSAGES = [
        HttpCode::BAD_REQUEST => Message::BAD_REQUEST,
        HttpCode::UNAUTHORIZED => Message::UNAUTHORIZED,
        HttpCode::FORBIDDEN => Message::FORBIDDEN,
        HttpCode::NOT_FOUND => Message::ROUTE_NOT_FOUND,
        HttpCode::METHOD_NOT_ALLOWED => Message::METHOD_NOT_ALLOWED,
        HttpCode::UNPROCESSABLE => Message::VALIDATION_FAILED,
        HttpCode::SERVICE_UNAVAILABLE => Message::SERVICE_UNAVAILABLE,
    ];

    protected function renderException($exception): void
    {
        [$code, $message, $errors] = $this->resolve($exception);

        $response = Yii::$app->has('response') ? Yii::$app->getResponse() : new Response();
        $response->format = Response::FORMAT_JSON;
        $response->data = ResponseHelper::error($message, $code, $errors);
        $response->setStatusCode($code);
        $response->send();
    }

    private function resolve(\Throwable $e): array
    {
        if ($e instanceof ApiException) {
            return [$e->statusCode, $e->getMessage(), $e->errors];
        }

        if ($e instanceof HttpException) {
            return [$e->statusCode, self::DEFAULT_MESSAGES[$e->statusCode] ?? $e->getMessage(), null];
        }

        if ($e instanceof StaleObjectException) {
            return [HttpCode::CONFLICT, Message::DATA_CHANGED, null];
        }

        if ($e instanceof IntegrityException) {
            return match ($e->errorInfo[0] ?? null) {
                self::PG_UNIQUE_VIOLATION => [HttpCode::CONFLICT, Message::DUPLICATE, null],
                self::PG_FOREIGN_KEY_VIOLATION => [HttpCode::CONFLICT, Message::IN_USE, null],
                self::PG_EXCLUSION_VIOLATION => [HttpCode::CONFLICT, Message::CONFLICT, null],
                self::PG_CHECK_VIOLATION => [HttpCode::UNPROCESSABLE, Message::VALIDATION_FAILED, null],
                default => $this->serverError($e),
            };
        }

        return $this->serverError($e);
    }

    private function serverError(\Throwable $e): array
    {
        Yii::error((string) $e, __METHOD__);

        $detail = YII_DEBUG ? ['exception' => get_class($e), 'detail' => $e->getMessage()] : null;

        return [HttpCode::INTERNAL_ERROR, Message::SERVER_ERROR, $detail];
    }
}
