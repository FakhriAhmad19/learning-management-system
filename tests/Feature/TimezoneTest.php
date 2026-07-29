<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_runs_in_jakarta_time(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', now()->timezoneName);
    }

    /**
     * Inti masalahnya: dengan UTC, tenggat "23:59" yang dimaksud pengajar
     * sebagai waktu WIB baru benar-benar habis pukul 06:59 keesokan harinya,
     * sehingga siswa mendapat 7 jam ekstra tanpa disengaja.
     */
    public function test_due_date_expires_at_the_hour_the_instructor_typed(): void
    {
        $course = Course::create([
            'instructor_id' => User::factory()->create()->id,
            'title' => 'Kursus Zona Waktu',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);

        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas Bertenggat',
            // Pengajar mengetik 1 Agustus 23:59 lewat DateTimePicker
            'due_date' => '2026-08-01 23:59:00',
            'max_score' => 100,
            'passing_score' => 60,
        ]);

        // Waktu "sekarang" ditulis dalam UTC secara eksplisit supaya ia
        // menyatakan SATU TITIK WAKTU NYATA, bukan angka yang ikut bergeser
        // mengikuti zona aplikasi. Tanpa ini, now() dan due_date sama-sama
        // berada di kerangka aplikasi sehingga perbandingannya selalu
        // konsisten — dan bug zona waktu tidak akan pernah tertangkap.

        // 16:00 UTC = 23:00 WIB, satu jam sebelum tenggat
        Carbon::setTestNow(Carbon::parse('2026-08-01 16:00:00', 'UTC'));
        $this->assertFalse($assignment->isOverdue(), 'Pukul 23:00 WIB seharusnya belum lewat tenggat 23:59.');

        // 17:00 UTC = 00:00 WIB keesokan hari, satu menit setelah tenggat.
        // Bila aplikasi berjalan di UTC, saat ini masih terbaca 17:00 dan
        // tugas dianggap belum terlambat — inilah 7 jam ekstra itu.
        Carbon::setTestNow(Carbon::parse('2026-08-01 17:00:00', 'UTC'));
        $this->assertTrue($assignment->isOverdue(), 'Pukul 00:00 WIB seharusnya sudah lewat tenggat 23:59.');

        Carbon::setTestNow();
    }

    public function test_stored_datetime_is_displayed_unchanged(): void
    {
        $course = Course::create([
            'instructor_id' => User::factory()->create()->id,
            'title' => 'Kursus Tampilan',
            'about' => 'deskripsi',
            'price' => 0,
            'status' => 'published',
        ]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'Modul 1', 'order' => 1]);

        $assignment = Assignment::create([
            'module_id' => $module->id,
            'title' => 'Tugas',
            'due_date' => '2026-08-01 23:59:00',
            'max_score' => 100,
            'passing_score' => 60,
        ]);

        // Yang diketik pengajar harus sama persis dengan yang dibaca ulang
        // dari database — tanpa pergeseran jam.
        $this->assertSame(
            '01 Aug 2026, 23:59',
            $assignment->fresh()->due_date->format('d M Y, H:i')
        );
    }
}
