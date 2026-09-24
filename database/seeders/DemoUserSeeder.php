<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'username' => 'admin',
                'email' => 'admin@demo.test',
                'nama_lengkap' => 'Administrator Demo',
                'nip_nis' => 'ADM-DEMO-001',
                'jenis_kelamin' => null,
                'role_id' => 1,
            ],
            [
                'username' => 'guru',
                'email' => 'guru@demo.test',
                'nama_lengkap' => 'Guru Demo Satu',
                'nip_nis' => 'GR-DEMO-001',
                'jenis_kelamin' => 'L',
                'role_id' => 2,
            ],
            [
                'username' => 'guru2',
                'email' => 'guru2@demo.test',
                'nama_lengkap' => 'Guru Demo Dua',
                'nip_nis' => 'GR-DEMO-002',
                'jenis_kelamin' => 'P',
                'role_id' => 2,
            ],
            [
                'username' => 'guru3',
                'email' => 'guru3@demo.test',
                'nama_lengkap' => 'Guru Demo Tiga',
                'nip_nis' => 'GR-DEMO-003',
                'jenis_kelamin' => 'L',
                'role_id' => 2,
            ],
            [
                'username' => 'guru4',
                'email' => 'guru4@demo.test',
                'nama_lengkap' => 'Guru Demo Empat',
                'nip_nis' => 'GR-DEMO-004',
                'jenis_kelamin' => 'P',
                'role_id' => 2,
            ],
            [
                'username' => 'guru5',
                'email' => 'guru5@demo.test',
                'nama_lengkap' => 'Guru Demo Lima',
                'nip_nis' => 'GR-DEMO-005',
                'jenis_kelamin' => 'L',
                'role_id' => 2,
            ],
            [
                'username' => 'kepsek',
                'email' => 'kepsek@demo.test',
                'nama_lengkap' => 'Kepala Sekolah Demo',
                'nip_nis' => 'KS-DEMO-001',
                'jenis_kelamin' => 'P',
                'role_id' => 4,
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                array_merge($user, [
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ])
            );
        }

        for ($i = 1; $i <= 36; $i++) {
            $number = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            User::updateOrCreate(
                ['username' => $i === 1 ? 'siswa' : "siswa{$number}"],
                [
                    'email' => $i === 1 ? 'siswa@demo.test' : "siswa{$number}@demo.test",
                    'nama_lengkap' => "Siswa Demo {$number}",
                    'nip_nis' => "SD-DEMO-{$number}",
                    'jenis_kelamin' => $i % 2 === 0 ? 'P' : 'L',
                    'password' => Hash::make('password'),
                    'role_id' => 3,
                    'is_active' => true,
                ]
            );
        }
    }
}
