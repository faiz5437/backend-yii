<?php

namespace app\components;

use app\components\http\HttpCode;
use app\components\http\Message;
use app\exceptions\ApiException;
use Yii;
use yii\base\ActionFilter;

/**
 * Pembatas jumlah request per alamat IP untuk endpoint publik (tanpa login).
 */
class RateLimitFilter extends ActionFilter
{
    public int $limit = 60;
    public int $window = 60;

    public function beforeAction($action): bool
    {
        $key = ['rate-limit', $action->uniqueId, Yii::$app->request->userIP];
        $cache = Yii::$app->cache;

        $now = time();
        $state = $cache->get($key);
        if (!is_array($state) || $state['reset'] <= $now) {
            $state = ['hits' => 0, 'reset' => $now + $this->window];
        }

        $state['hits']++;
        $cache->set($key, $state, $state['reset'] - $now);

        if ($state['hits'] > $this->limit) {
            Yii::$app->response->headers->set('Retry-After', (string) ($state['reset'] - $now));
            throw new ApiException(HttpCode::TOO_MANY_REQUESTS, Message::TOO_MANY_REQUESTS);
        }

        return true;
    }
}
