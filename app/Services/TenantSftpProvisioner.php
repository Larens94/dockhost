<?php

namespace App\Services;

use App\Models\Pool;
use App\Models\Site;
use App\Models\SiteSftpAccount;
use Illuminate\Support\Str;

class TenantSftpProvisioner
{
    public function ensure(Site $site, Pool $pool): SiteSftpAccount
    {
        return SiteSftpAccount::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'pool_id' => $pool->id,
                'username' => 'sftp'.$site->id,
                'password' => Str::password(20, symbols: false),
                'chroot_path' => '/var/sites/'.$site->id,
                'status' => 'reserved',
            ]
        );
    }
}
