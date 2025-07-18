<?php

namespace LaraGram\Surge\Tables;

class TableFactory
{
    /**
     * Creates a new Swoole Table with the given size.
     *
     * @return \Swoole\Table
     */
    public static function make($size)
    {
        static::ensureDependenciesAreLoaded();

        return extension_loaded('openswoole')
            ? new OpenSwooleTable($size, 1)
            : new SwooleTable($size, 1);
    }

    /**
     * Because those tables may be required without composer
     * we ensure the table's dependencies are loaded.
     */
    protected static function ensureDependenciesAreLoaded()
    {
        require_once __DIR__.'/Concerns/EnsuresColumnSizes.php';

        extension_loaded('openswoole')
            ? require_once __DIR__.'/OpenSwooleTable.php'
            : require_once __DIR__.'/SwooleTable.php';
    }
}
