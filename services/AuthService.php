<?php

namespace app\services;

use app\components\http\Message;
use app\exceptions\ApiException;
use app\models\RefreshToken;
use app\models\User;
use Yii;
use yii\db\Expression;

final class AuthService
{
    public function login(string $username, string $password, array $client = []): array
    {
        $user = User::findOne(['username' => strtolower(trim($username))]);

        if (!$user || !$user->validatePassword($password)) {
            throw ApiException::unauthorized(Message::LOGIN_FAILED);
        }

        if (!$user->is_active) {
            throw ApiException::forbidden(Message::ACCOUNT_INACTIVE);
        }

        $user->updateAttributes(['last_login_at' => new Expression('NOW()')]);

        return $this->issueTokens($user, $client);
    }

    /**
     * Refresh token dirotasi: token lama dicabut dan diganti token baru.
     * Jika token yang sudah dicabut dipakai lagi, semua sesi user dicabut karena tokennya kemungkinan bocor.
     */
    public function refresh(string $rawToken, array $client = []): array
    {
        return Yii::$app->db->transaction(function () use ($rawToken, $client) {
            $token = RefreshToken::findBySql(
                'SELECT * FROM refresh_token WHERE token_hash = :hash FOR UPDATE',
                [':hash' => $this->hash($rawToken)]
            )->one();

            if (!$token) {
                throw ApiException::unauthorized(Message::REFRESH_TOKEN_INVALID);
            }

            if ($token->revoked_at !== null) {
                $this->revokeAllForUser($token->user_id);
                throw ApiException::unauthorized(Message::REFRESH_TOKEN_INVALID);
            }

            if (!$token->isUsable() || !$token->user?->is_active) {
                throw ApiException::unauthorized(Message::REFRESH_TOKEN_INVALID);
            }

            $token->updateAttributes(['revoked_at' => new Expression('NOW()')]);

            return $this->issueTokens($token->user, $client);
        });
    }

    public function logout(?string $rawToken): void
    {
        if ($rawToken) {
            RefreshToken::updateAll(
                ['revoked_at' => new Expression('NOW()')],
                ['token_hash' => $this->hash($rawToken), 'revoked_at' => null]
            );
        }
    }

    public function revokeAllForUser(string $userId): void
    {
        RefreshToken::updateAll(['revoked_at' => new Expression('NOW()')], ['user_id' => $userId, 'revoked_at' => null]);
    }

    public function profile(User $user): array
    {
        $tenagaMedis = $user->tenagaMedis;

        return [
            'id' => $user->id,
            'username' => $user->username,
            'nama' => $user->nama,
            'email' => $user->email,
            'roles' => $user->roleNames(),
            'permissions' => $user->permissionNames(),
            'tenaga_medis' => $tenagaMedis ? [
                'id' => $tenagaMedis->id,
                'nama' => $tenagaMedis->nama,
                'jenis' => $tenagaMedis->jenis,
                'poli_id' => $tenagaMedis->poli_id,
                'poli' => $tenagaMedis->poli->nama ?? null,
            ] : null,
        ];
    }

    private function issueTokens(User $user, array $client): array
    {
        $profile = $this->profile($user);

        $accessToken = Yii::$app->jwt->issue($user->id, [
            'name' => $user->nama,
            'username' => $user->username,
            'roles' => $profile['roles'],
            'permissions' => $profile['permissions'],
            'tenaga_medis_id' => $user->tenaga_medis_id,
        ]);

        $rawRefresh = Yii::$app->security->generateRandomString(64);
        $ttl = Yii::$app->params['jwt']['refreshTtl'];

        (new RefreshToken([
            'user_id' => $user->id,
            'token_hash' => $this->hash($rawRefresh),
            'expires_at' => date(DATE_ATOM, time() + $ttl),
            'user_agent' => mb_substr((string) ($client['user_agent'] ?? ''), 0, 255),
            'ip_address' => $client['ip'] ?? null,
        ]))->save(false);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => Yii::$app->jwt->accessTtl(),
            'refresh_token' => $rawRefresh,
            'refresh_expires_in' => $ttl,
            'user' => $profile,
        ];
    }

    private function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }
}
