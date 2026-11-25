<?php

namespace app\components;

use yii\db\IntegrityException;

final class DbError
{
    public static function isUniqueViolation(\Throwable $e, ?string $constraint = null): bool
    {
        if (!$e instanceof IntegrityException || ($e->errorInfo[0] ?? null) !== '23505') {
            return false;
        }

        return $constraint === null || str_contains($e->getMessage(), $constraint);
    }
}
