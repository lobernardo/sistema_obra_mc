<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Model events stay enabled: the `Pedido::creating` hook fills
     * `requested_at` and `data_prevista` (NOT NULL) for every demo pedido.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
