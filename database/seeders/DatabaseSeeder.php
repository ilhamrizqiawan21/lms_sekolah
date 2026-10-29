<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('production') && config('app.demo_mode', false)) {
            throw new \RuntimeException('Demo seeder diblokir di production. Gunakan EmptyProductSeeder dengan DEFAULT_ADMIN_PASSWORD yang kuat.');
        }

        $this->call([
            RoleSeeder::class,
            SchoolSettingSeeder::class,
        ]);

        if (! config('app.demo_mode', false)) {
            return;
        }

        $this->call([
            DemoUserSeeder::class,
            DemoAcademicSeeder::class,
            JadwalMengajarSeeder::class,
            DemoLmsSeeder::class,
            DemoSupportSeeder::class,
        ]);
    }
}
