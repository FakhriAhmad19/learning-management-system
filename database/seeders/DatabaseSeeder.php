<?php

namespace Database\Seeders;

use App\Models\User;
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
        // User::factory(10)->create();

        // Idempotent: aman dijalankan ulang (mis. saat container restart)
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => bcrypt('password'), 'email_verified_at' => now()]
        );

        $this->call([
            // Demo inti: akun yang didokumentasikan di README + jalur belajar
            LmsDummySeeder::class,
            // Keluasan: banyak pengajar, kursus, dan siswa dengan progres beragam
            DemoContentSeeder::class,
        ]);
    }
}
