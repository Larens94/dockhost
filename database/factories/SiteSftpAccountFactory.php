<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteSftpAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteSftpAccount>
 */
class SiteSftpAccountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'username' => 'sftp1',
            'password' => 'secretpass1',
            'chroot_path' => '/var/sites/1',
            'status' => 'reserved',
        ];
    }
}
