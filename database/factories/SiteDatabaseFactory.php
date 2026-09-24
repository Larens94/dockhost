<?php

namespace Database\Factories;

use App\Models\Site;
use App\Models\SiteDatabase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteDatabase>
 */
class SiteDatabaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'engine' => 'mariadb',
            'schema_name' => 'db_app',
            'username' => 'u_app',
            'password' => 'secretpass1',
            'host' => '10.0.0.10',
            'port' => 3306,
            'status' => 'reserved',
        ];
    }
}
