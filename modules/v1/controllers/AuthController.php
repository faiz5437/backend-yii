<?php

namespace app\modules\v1\controllers;

use app\components\ApiController;
use app\components\Form;
use app\components\http\Message;
use app\components\http\ResponseHelper;
use app\exceptions\ApiException;
use app\services\AuthService;
use Yii;
use yii\web\Cookie;

class AuthController extends ApiController
{
    private const REFRESH_COOKIE = 'refresh_token';

    protected array $publicActions = ['login', 'refresh', 'logout'];

    public function __construct($id, $module, private readonly AuthService $auth = new AuthService(), $config = [])
    {
        parent::__construct($id, $module, $config);
    }

    protected function verbs(): array
    {
        return ['login' => ['POST'], 'refresh' => ['POST'], 'logout' => ['POST'], 'me' => ['GET']];
    }

    public function actionLogin(): array
    {
        $form = Form::validate($this->body(), [
            [['username', 'password'], 'required'],
            [['username', 'password'], 'string', 'max' => 100],
        ]);

        $result = $this->auth->login($form->username, $form->password, $this->client());

        return ResponseHelper::success($this->withCookie($result), Message::LOGIN_SUCCESS);
    }

    public function actionRefresh(): array
    {
        $raw = $this->refreshToken() ?? throw ApiException::unauthorized(Message::REFRESH_TOKEN_INVALID);

        $result = $this->auth->refresh($raw, $this->client());

        return ResponseHelper::success($this->withCookie($result), Message::TOKEN_REFRESHED);
    }

    public function actionLogout(): array
    {
        $this->auth->logout($this->refreshToken());
        Yii::$app->response->cookies->remove(new Cookie(['name' => self::REFRESH_COOKIE, 'path' => '/api/v1/auth']));

        return ResponseHelper::success(null, Message::LOGOUT_SUCCESS);
    }

    public function actionMe(): array
    {
        return ResponseHelper::success($this->auth->profile($this->user()), Message::FETCHED);
    }

    /**
     * Refresh token hanya dikirim lewat cookie httpOnly sehingga tidak bisa dibaca JavaScript.
     */
    private function withCookie(array $result): array
    {
        Yii::$app->response->cookies->add(new Cookie([
            'name' => self::REFRESH_COOKIE,
            'value' => $result['refresh_token'],
            'httpOnly' => true,
            'secure' => Yii::$app->request->isSecureConnection,
            'sameSite' => Cookie::SAME_SITE_STRICT,
            'path' => '/api/v1/auth',
            'expire' => time() + $result['refresh_expires_in'],
        ]));

        unset($result['refresh_token'], $result['refresh_expires_in']);

        return $result;
    }

    private function refreshToken(): ?string
    {
        return Yii::$app->request->cookies->getValue(self::REFRESH_COOKIE)
            ?: ($this->body()['refresh_token'] ?? null);
    }

    private function client(): array
    {
        return [
            'user_agent' => Yii::$app->request->userAgent,
            'ip' => Yii::$app->request->userIP,
        ];
    }
}
