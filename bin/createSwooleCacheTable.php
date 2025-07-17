<?php

use LaraGram\Surge\Tables\TableFactory;
use Swoole\Table;

require_once __DIR__.'/../src/Tables/TableFactory.php';

if ($serverState['surgeConfig']['cache'] ?? false) {
    $cacheTable = TableFactory::make(
        $serverState['surgeConfig']['cache']['rows'] ?? 1000
    );

    $cacheTable->column('value', Table::TYPE_STRING, $serverState['surgeConfig']['cache']['bytes'] ?? 10000);
    $cacheTable->column('expiration', Table::TYPE_INT);

    $cacheTable->create();

    return $cacheTable;
}
