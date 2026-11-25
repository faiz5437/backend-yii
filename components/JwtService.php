<?php

namespace app\components;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;
use Yii;
use yii\base\Component;

class JwtService extends Component
{
    private const ALGORITHM = 'RS256';

    private ?string $privateKey = null;
    private ?string $publicKey = null;

    public function issue(string $subject, array $claims = []): string
    {
        $now = time();
        $config = Yii::$app->params['jwt'];

        $payload = array_merge($claims, [
            'iss' => $config['issuer'],
            'sub' => $subject,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $config['accessTtl'],
            'jti' => Uuid::uuid7()->toString(),
        ]);

        return JWT::encode($payload, $this->privateKey(), self::ALGORITHM);
    }

    public function decode(string $token): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->publicKey(), self::ALGORITHM));

            return $payload->iss === Yii::$app->params['jwt']['issuer'] ? $payload : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function accessTtl(): int
    {
        return Yii::$app->params['jwt']['accessTtl'];
    }

    private function privateKey(): string
    {
        return $this->privateKey ??= file_get_contents(Yii::$app->params['jwt']['privateKeyPath']);
    }

    private function publicKey(): string
    {
        return $this->publicKey ??= file_get_contents(Yii::$app->params['jwt']['publicKeyPath']);
    }
}
