<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hanya jalankan foreign key check jika menggunakan MySQL
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            Role::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $roles = [
            ['id' => 1, 'nama_role' => 'admin'],
            ['id' => 2, 'nama_role' => 'guru'],
            ['id' => 3, 'nama_role' => 'siswa'],
            ['id' => 4, 'nama_role' => 'kepala_sekolah'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['id' => $role['id']],
                ['nama_role' => $role['nama_role']]
            );
        }
    }
}
