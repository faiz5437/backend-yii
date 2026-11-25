<?php

/**
 * Gabungan routing semua versi API. Versi baru cukup ditambahkan di sini,
 * misalnya require dirname(__DIR__) . '/modules/v2/routes.php'.
 */
return array_merge(
    require dirname(__DIR__) . '/modules/v1/routes.php',
);
