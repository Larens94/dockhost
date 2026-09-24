<?php

namespace App\Contracts;

use App\Models\Pool;

interface DatabaseAdmin
{
    /**
     * Run administrative SQL against a shared pool.
     *
     * @param  list<string>  $statements
     */
    public function exec(Pool $pool, array $statements): void;
}
