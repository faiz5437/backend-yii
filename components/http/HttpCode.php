<?php

namespace app\components\http;

final class HttpCode
{
    public const OK = 200;
    public const CREATED = 201;
    public const ACCEPTED = 202;
    public const NO_CONTENT = 204;

    public const BAD_REQUEST = 400;
    public const UNAUTHORIZED = 401;
    public const FORBIDDEN = 403;
    public const NOT_FOUND = 404;
    public const METHOD_NOT_ALLOWED = 405;
    public const CONFLICT = 409;
    public const GONE = 410;
    public const PAYLOAD_TOO_LARGE = 413;
    public const UNSUPPORTED_MEDIA_TYPE = 415;
    public const UNPROCESSABLE = 422;
    public const LOCKED = 423;
    public const TOO_MANY_REQUESTS = 429;

    public const INTERNAL_ERROR = 500;
    public const NOT_IMPLEMENTED = 501;
    public const BAD_GATEWAY = 502;
    public const SERVICE_UNAVAILABLE = 503;
    public const GATEWAY_TIMEOUT = 504;

    public static function isSuccess(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }
}
