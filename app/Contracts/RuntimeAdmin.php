<?php

namespace App\Contracts;

use App\Models\Pool;

interface RuntimeAdmin
{
    public function canManage(Pool $pool): bool;

    public function createSftpUser(Pool $pool, string $username, string $password, string $chroot): void;
}
