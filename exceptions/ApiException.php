<?php

namespace app\exceptions;

use app\components\http\HttpCode;
use app\components\http\Message;
use yii\base\Model;
use yii\web\HttpException;

class ApiException extends HttpException
{
    public function __construct(int $status, string $message, public readonly mixed $errors = null)
    {
        parent::__construct($status, $message);
    }

    public static function badRequest(string $message = Message::BAD_REQUEST, mixed $errors = null): self
    {
        return new self(HttpCode::BAD_REQUEST, $message, $errors);
    }

    public static function unauthorized(string $message = Message::UNAUTHORIZED): self
    {
        return new self(HttpCode::UNAUTHORIZED, $message);
    }

    public static function forbidden(string $message = Message::FORBIDDEN): self
    {
        return new self(HttpCode::FORBIDDEN, $message);
    }

    public static function notFound(string $message = Message::NOT_FOUND): self
    {
        return new self(HttpCode::NOT_FOUND, $message);
    }

    public static function conflict(string $message = Message::CONFLICT, mixed $errors = null): self
    {
        return new self(HttpCode::CONFLICT, $message, $errors);
    }

    public static function unprocessable(string $message = Message::VALIDATION_FAILED, mixed $errors = null): self
    {
        return new self(HttpCode::UNPROCESSABLE, $message, $errors);
    }

    public static function fromModel(Model $model, string $message = Message::VALIDATION_FAILED): self
    {
        return self::unprocessable($message, $model->getErrors());
    }
}
