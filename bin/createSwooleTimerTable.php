<?php

use LaraGram\Surge\Tables\TableFactory;
use Swoole\Table;

require_once __DIR__.'/../src/Tables/TableFactory.php';

if (($serverState['surgeConfig']['max_execution_time'] ?? 0) > 0) {
    $timerTable = TableFactory::make($serverState['surgeConfig']['max_timer_table_size'] ?? 250);

    $timerTable->column('worker_pid', Table::TYPE_INT);
    $timerTable->column('time', Table::TYPE_INT);
    $timerTable->column('fd', Table::TYPE_INT);

    $timerTable->create();

    return $timerTable;
}

return null;
