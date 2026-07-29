<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class LmsDummySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastikan Role Spatie Tersedia
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $instructorRole = Role::firstOrCreate(['name' => 'Instructor']);
        $studentRole = Role::firstOrCreate(['name' => 'Student']);

        // 1b. Buat Akun Admin (akses penuh ke Panel Filament)
        $admin = User::firstOrCreate(
            ['email' => 'admin@lms.com'],
            [
                'name' => 'Administrator',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole($adminRole);

        // 2. Buat Akun Pengajar (Instructor)
        $instructor = User::firstOrCreate(
            ['email' => 'instructor@lms.com'],
            [
                'name' => 'Budi Pengajar, M.Kom',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
        $instructor->assignRole($instructorRole);

        // 3. Buat Akun Siswa Dummy (Student)
        $student = User::firstOrCreate(
            ['email' => 'student@lms.com'],
            [
                'name' => 'Siswa Belajar',
                'password' => bcrypt('password123'),
                'email_verified_at' => now(),
            ]
        );
        $student->assignRole($studentRole);

        // 3b. Kategori kursus (taksonomi katalog)
        $katBackend = Category::firstOrCreate(['slug' => 'backend'], ['name' => 'Backend']);
        $katFrontend = Category::firstOrCreate(['slug' => 'frontend'], ['name' => 'Frontend']);
        Category::firstOrCreate(['slug' => 'data-science'], ['name' => 'Data Science']);

        // Hindari duplikasi data dummy saat seeder dijalankan ulang
        if (Course::exists()) {
            return;
        }

        // 4. Buat Dummy Data Kursus 1: Laravel 11 Mastery
        $course1 = Course::create([
            'instructor_id' => $instructor->id,
            'category_id' => $katBackend->id,
            'title' => 'Laravel Framework Mastery dari Nol',
            'slug' => Str::slug('Laravel Framework Mastery dari Nol'),
            'about' => 'Pelajari framework PHP terpopuler di dunia dari konsep MVC, Database, hingga membangun REST API dan Authentication.',
            'thumbnail' => null,
            'price' => 0, // Semua kelas akses gratis
            'status' => 'published',
        ]);

        // Modul 1
        $module1 = Module::create([
            'course_id' => $course1->id,
            'title' => 'Bab 1: Pengenalan & Instalasi Laravel',
            'order' => 1,
        ]);

        $lesson1 = Lesson::create([
            'module_id' => $module1->id,
            'title' => 'Apa itu Laravel & Konsep MVC',
            'slug' => Str::slug('Apa itu Laravel & Konsep MVC'),
            'content' => '<h2>Mengenal Laravel</h2><p>Laravel adalah framework PHP modern yang mengedepankan sintaks ekspresif dan produktivitas developer. Pada materi ini kita akan memahami fondasi arsitektur yang dipakai Laravel.</p><h3>Pola MVC</h3><p>MVC memisahkan aplikasi menjadi tiga lapisan: <strong>Model</strong> (data & logika bisnis), <strong>View</strong> (tampilan), dan <strong>Controller</strong> (penghubung request dengan respons). Pemisahan ini membuat kode lebih rapi dan mudah dirawat.</p><ul><li>Model: berinteraksi dengan database via Eloquent ORM.</li><li>View: berkas Blade yang dirender menjadi HTML.</li><li>Controller: mengatur alur dan mengembalikan respons.</li></ul>',
            'is_free_preview' => true,
            'order' => 1,
        ]);

        Lesson::create([
            'module_id' => $module1->id,
            'title' => 'Instalasi Composer & Laravel via Terminal',
            'slug' => Str::slug('Instalasi Composer & Laravel via Terminal'),
            'content' => '<h2>Menyiapkan Lingkungan Lokal</h2><p>Sebelum membangun aplikasi, kita perlu memasang Composer sebagai package manager PHP, lalu membuat proyek Laravel baru.</p><p>Jalankan perintah berikut di terminal untuk membuat proyek:</p><p><code>composer create-project laravel/laravel nama-proyek</code></p><p>Setelah selesai, jalankan <code>php artisan serve</code> dan buka <em>http://localhost:8000</em> di browser. Unduh modul latihan pada lampiran untuk panduan langkah demi langkah.</p>',
            'is_free_preview' => false,
            'order' => 2,
        ]);

        // Modul 2
        $module2 = Module::create([
            'course_id' => $course1->id,
            'title' => 'Bab 2: Database & Eloquent ORM',
            'order' => 2,
        ]);

        Lesson::create([
            'module_id' => $module2->id,
            'title' => 'Memahami Migration & Seeder',
            'slug' => Str::slug('Memahami Migration & Seeder'),
            'content' => '<h2>Migration sebagai Version Control Database</h2><p>Migration memungkinkan kita mendefinisikan skema tabel menggunakan kode PHP, sehingga struktur database dapat dibagikan dan direproduksi dengan mudah oleh seluruh tim.</p><p>Gunakan <code>php artisan make:migration</code> untuk membuat berkas migration, lalu <code>php artisan migrate</code> untuk menerapkannya. <strong>Seeder</strong> dipakai untuk mengisi data awal ke dalam tabel.</p>',
            'is_free_preview' => false,
            'order' => 1,
        ]);

        // Tugas Bab 2 (course 1) — dinilai manual oleh pengajar
        Assignment::create([
            'module_id' => $module2->id,
            'title' => 'Tugas: Buat Migration & Seeder Sederhana',
            'description' => '<p>Buat sebuah migration untuk tabel <code>products</code> berisi kolom <strong>name</strong>, <strong>price</strong>, dan <strong>stock</strong>. Lalu buat seeder yang mengisi minimal 5 data contoh.</p><p>Kumpulkan dalam bentuk berkas ZIP berisi kedua file tersebut, atau tempel kodenya pada kolom jawaban.</p>',
            'due_date' => now()->addDays(14),
            'max_score' => 100,
            'passing_score' => 60,
        ]);

        // Kuis akhir Bab 1 (course 1)
        $quiz = Quiz::create([
            'module_id' => $module1->id,
            'title' => 'Kuis Bab 1: Dasar Laravel',
            'passing_score' => 70,
            'max_attempts' => 3,
            'time_limit_minutes' => 10,
        ]);

        $q1 = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Pola arsitektur yang digunakan Laravel adalah?',
            'order' => 1,
        ]);
        QuestionOption::insert([
            ['question_id' => $q1->id, 'option_text' => 'MVC (Model-View-Controller)', 'is_correct' => true, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q1->id, 'option_text' => 'MVVM', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q1->id, 'option_text' => 'Singleton', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $q2 = Question::create([
            'quiz_id' => $quiz->id,
            'question' => 'Perintah untuk menjalankan migration di Laravel adalah?',
            'order' => 2,
        ]);
        QuestionOption::insert([
            ['question_id' => $q2->id, 'option_text' => 'php artisan migrate', 'is_correct' => true, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q2->id, 'option_text' => 'composer migrate', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
            ['question_id' => $q2->id, 'option_text' => 'npm run migrate', 'is_correct' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Soal Benar/Salah — opsinya dibuat otomatis oleh model Question
        Question::create([
            'quiz_id' => $quiz->id,
            'type' => Question::TYPE_TRUE_FALSE,
            'question' => 'Eloquent adalah ORM bawaan Laravel.',
            'order' => 3,
            'true_false_answer' => 'benar',
        ]);

        // 5. Buat Dummy Data Kursus 2: Vue.js 3 Fundamentals
        $course2 = Course::create([
            'instructor_id' => $instructor->id,
            'category_id' => $katFrontend->id,
            'title' => 'Membangun Web Modern dengan Vue.js 3',
            'slug' => Str::slug('Membangun Web Modern dengan Vue.js 3'),
            'about' => 'Kuasai JavaScript Frontend Framework paling fleksibel untuk membuat Single Page Application (SPA).',
            'thumbnail' => null,
            'price' => 0, // Kursus Gratis
            'status' => 'published',
        ]);

        $moduleVue = Module::create([
            'course_id' => $course2->id,
            'title' => 'Bab 1: Dasar-dasar Vue 3 Component',
            'order' => 1,
        ]);

        $lessonVue = Lesson::create([
            'module_id' => $moduleVue->id,
            'title' => 'Reactivity & Composition API',
            'slug' => Str::slug('Reactivity & Composition API'),
            'content' => '<h2>Sistem Reaktivitas Vue 3</h2><p>Vue 3 memperkenalkan <strong>Composition API</strong> yang membuat logika komponen lebih modular dan mudah digunakan kembali.</p><p>Dua fungsi inti reaktivitas adalah <code>ref()</code> untuk nilai primitif dan <code>reactive()</code> untuk objek. Keduanya membuat data secara otomatis memperbarui tampilan saat nilainya berubah.</p>',
            'is_free_preview' => true,
            'order' => 1,
        ]);

        // 5b. Jalur Belajar contoh: Laravel dulu, baru Vue.js
        $path = LearningPath::firstOrCreate(
            ['slug' => 'web-developer-fullstack'],
            [
                'title' => 'Web Developer Fullstack',
                'description' => 'Mulai dari backend dengan Laravel, lalu lanjut ke frontend modern dengan Vue.js 3. Kursus terbuka satu per satu sesuai urutan.',
                'status' => 'published',
            ]
        );
        $path->courses()->syncWithoutDetaching([
            $course1->id => ['order' => 1],
            $course2->id => ['order' => 2],
        ]);

        // 6. Buat Dummy Pendaftaran (Enrollment) agar dashboard punya data.
        // Progres tidak pernah ditulis langsung — selalu dihitung dari materi
        // yang benar-benar ditandai selesai, supaya konsisten dengan poin.
        $activeEnrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course1->id,
            'amount_paid' => 0,
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        $student->completedLessons()->syncWithoutDetaching([
            $lesson1->id => ['completed_at' => now()],
        ]);
        $activeEnrollment->recalculateProgress();

        $completedEnrollment = Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course2->id,
            'amount_paid' => 0,
            'status' => 'active',
            'progress_percentage' => 0,
        ]);

        // Tandai materinya benar-benar selesai, lalu biarkan sistem yang
        // menghitung progres. Sebelumnya progres 100% ditulis langsung tanpa
        // baris lesson_user, sehingga data seed bertentangan dengan dirinya
        // sendiri dan poin gamifikasi tidak punya sumber untuk dihitung.
        $student->completedLessons()->syncWithoutDetaching([
            $lessonVue->id => ['completed_at' => now()],
        ]);
        $completedEnrollment->recalculateProgress();
    }
}
