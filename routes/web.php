<?php

use App\Http\Controllers\AchievementController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearningPathController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuizController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/courses/{slug}', [HomeController::class, 'show'])->name('courses.show');

// Katalog jalur belajar — bisa dilihat tanpa login
Route::get('/paths', [LearningPathController::class, 'index'])->name('paths.index');
Route::get('/paths/{path:slug}', [LearningPathController::class, 'show'])->name('paths.show');

// Fitur yang membutuhkan login (Siswa)
Route::middleware('auth')->group(function () {
    // Halaman profil / akun — tetap bisa diakses meski email belum terverifikasi
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Fitur belajar hanya untuk pengguna dengan email terverifikasi
    Route::middleware('verified')->group(function () {
        // Dashboard siswa: daftar kursus yang diikuti + progres
        Route::get('/my-courses', [HomeController::class, 'myCourses'])->name('my-courses');

        // Bergabung / keluar dari jalur belajar
        Route::post('/paths/{path}/join', [LearningPathController::class, 'join'])->name('paths.join');
        Route::delete('/paths/{path}/leave', [LearningPathController::class, 'leave'])->name('paths.leave');

        // Gamifikasi: poin, badge, dan papan peringkat per kursus
        Route::get('/achievements', [AchievementController::class, 'index'])->name('achievements.index');
        Route::get('/leaderboard/{course:slug}', [AchievementController::class, 'leaderboard'])->name('leaderboard.show');

        // Notifikasi in-app (tugas baru, tugas dinilai, kelas selesai)
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

        // Rekap nilai siswa (kuis + tugas) lintas kelas
        Route::get('/my-grades', [GradeController::class, 'index'])->name('grades.index');

        // Pendaftaran kelas (Enrollment Engine)
        Route::post('/courses/{course}/enroll', [EnrollmentController::class, 'store'])->name('courses.enroll');

        // Ruang Belajar (Course Player) — {course} di-bind via slug
        Route::get('/learn/{course:slug}/{lesson?}', [LearnController::class, 'show'])->name('learn.show');

        // Tandai materi selesai (Progress Tracking)
        Route::post('/learn/{course:slug}/{lesson}/complete', [LearnController::class, 'complete'])->name('learn.complete');

        // Sertifikat kelulusan (untuk enrollment yang sudah 100%)
        Route::get('/certificate/{course:slug}', [CertificateController::class, 'show'])->name('certificate.show');

        // Kuis akhir bab (pilihan ganda, dinilai otomatis)
        Route::get('/learn/{course:slug}/quiz/{quiz}', [QuizController::class, 'show'])->name('quiz.show');
        Route::post('/learn/{course:slug}/quiz/{quiz}', [QuizController::class, 'submit'])->name('quiz.submit');

        // Diskusi / tanya-jawab per materi
        Route::post('/learn/{course:slug}/{lesson}/discussions', [DiscussionController::class, 'store'])->name('discussions.store');
        Route::delete('/learn/{course:slug}/discussions/{discussion}', [DiscussionController::class, 'destroy'])->name('discussions.destroy');

        // Tugas (dikumpulkan siswa, dinilai manual oleh pengajar)
        Route::get('/learn/{course:slug}/assignment/{assignment}', [AssignmentController::class, 'show'])->name('assignment.show');
        Route::post('/learn/{course:slug}/assignment/{assignment}', [AssignmentController::class, 'submit'])->name('assignment.submit');
    });
});
