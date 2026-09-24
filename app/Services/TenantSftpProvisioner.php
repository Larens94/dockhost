<?php

namespace App\Services;

use App\Contracts\RuntimeAdmin;
use App\Models\Pool;
use App\Models\Site;
use App\Models\SiteSftpAccount;
use Illuminate\Support\Str;
use Throwable;

class TenantSftpProvisioner
{
    public function __construct(private RuntimeAdmin $runtime) {}

    public function ensure(Site $site, Pool $pool): SiteSftpAccount
    {
        $existing = SiteSftpAccount::query()->where('site_id', $site->id)->first();

        if ($existing && $existing->status === 'provisioned' && (int) $existing->pool_id === $pool->id) {
            return $existing;
        }

        $account = SiteSftpAccount::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'pool_id' => $pool->id,
                'username' => $existing?->username ?? 'sftp'.$site->id,
                'password' => $existing?->password ?: Str::password(20, symbols: false),
                'chroot_path' => '/var/sites/'.$site->id,
                'status' => 'reserved',
            ]
        );

        if (! $this->runtime->canManage($pool)) {
            return $account;
        }

        try {
            $this->runtime->createSftpUser($pool, $account->username, (string) $account->password, $account->chroot_path);
            $account->status = 'provisioned';
            $account->save();
        } catch (Throwable) {
            $account->status = 'reserved';
            $account->save();
        }

        return $account;
    }
}
