<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([CatalogSeeder::class, PlanSeeder::class]);

        if (app()->environment('local')) {
            $this->call(DemoSeeder::class);
        }
    }
}
