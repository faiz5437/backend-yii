<?php

require __DIR__ . '/../config/bootstrap.php';

$config = require __DIR__ . '/../config/console.php';

new yii\console\Application($config);
