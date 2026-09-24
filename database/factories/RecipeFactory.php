<?php

namespace Database\Factories;

use App\Models\Recipe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Recipe>
 */
class RecipeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Laravel App',
            'slug' => fake()->unique()->slug(2),
            'stack' => 'laravel',
            'summary' => 'PHP application on shared pools',
            'version' => '1.0.0',
            'status' => 'stable',
            'enabled' => true,
            'sort' => 10,
            'requires' => ['database', 'storage', 'runtime'],
            'steps' => [
                ['op' => 'ensure_database'],
                ['op' => 'ensure_storage_path'],
                ['op' => 'ensure_sftp_user'],
                ['op' => 'deploy_application'],
                ['op' => 'attach_domain_ssl'],
            ],
        ];
    }
}
