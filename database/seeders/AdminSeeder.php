<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! config('app.demo_mode', false) || app()->environment('production')) {
            return;
        }

        $this->call(DemoUserSeeder::class);
    }
}
