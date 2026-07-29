<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Data demo tambahan: banyak pengajar, kursus, dan siswa dengan progres
 * yang beragam — supaya katalog, buku nilai, laporan, dan papan peringkat
 * punya isi yang terasa nyata.
 *
 * LmsDummySeeder tetap memegang demo inti (akun yang didokumentasikan di
 * README dan jalur belajar contoh); seeder ini hanya menambah keluasan.
 *
 * Progres TIDAK PERNAH ditulis sebagai angka. Materi ditandai selesai lalu
 * Enrollment::recalculateProgress() yang menghitung — sehingga poin, badge,
 * dan status kelulusan ikut konsisten dengan datanya.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $instructorRole = Role::firstOrCreate(['name' => 'Instructor']);
        $studentRole = Role::firstOrCreate(['name' => 'Student']);

        $categories = $this->seedCategories();
        $instructors = $this->seedInstructors($instructorRole);

        // Hindari menumpuk kursus bila seeder dijalankan berulang
        if (Course::count() > 2) {
            $this->command?->info('Data demo tambahan sudah ada — dilewati.');

            return;
        }

        $courses = $this->seedCourses($categories, $instructors);
        $students = $this->seedStudents($studentRole);

        $this->seedEnrollments($courses, $students);
    }

    /**
     * @return Collection<string, Category>
     */
    private function seedCategories(): Collection
    {
        return collect([
            'backend' => 'Backend',
            'frontend' => 'Frontend',
            'data-science' => 'Data Science',
            'mobile' => 'Mobile',
            'devops' => 'DevOps',
            'ui-ux' => 'UI/UX Design',
        ])->map(fn (string $name, string $slug) => Category::firstOrCreate(['slug' => $slug], ['name' => $name]));
    }

    /**
     * @return Collection<string, User>
     */
    private function seedInstructors(Role $role): Collection
    {
        $people = [
            'budi' => ['Budi Pengajar, M.Kom', 'instructor@lms.com'],
            'sari' => ['Sari Wulandari, S.Kom', 'sari@lms.com'],
            'rizky' => ['Rizky Pratama', 'rizky@lms.com'],
            'dewi' => ['Dewi Anggraini, M.T.', 'dewi@lms.com'],
            'agus' => ['Agus Setiawan', 'agus@lms.com'],
        ];

        return collect($people)->map(function (array $person) use ($role) {
            [$name, $email] = $person;

            $user = User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => bcrypt('password123'), 'email_verified_at' => now()]
            );
            $user->assignRole($role);

            return $user;
        });
    }

    /**
     * Definisi kursus: kategori, pengajar, dan kurikulumnya.
     *
     * @return Collection<int, Course>
     */
    private function seedCourses(Collection $categories, Collection $instructors): Collection
    {
        $definitions = [
            [
                'title' => 'Dasar Pemrograman PHP untuk Pemula',
                'about' => 'Mulai dari nol: sintaks PHP, tipe data, percabangan, perulangan, fungsi, hingga membaca dan menulis berkas.',
                'category' => 'backend', 'instructor' => 'budi',
                'modules' => [
                    ['Bab 1: Mengenal PHP', ['Menyiapkan Lingkungan Kerja', 'Variabel dan Tipe Data']],
                    ['Bab 2: Alur Program', ['Percabangan if dan switch', 'Perulangan for dan while', 'Membuat Fungsi Sendiri']],
                ],
                'quiz' => ['Kuis: Dasar Sintaks PHP', 'Tag pembuka skrip PHP yang benar adalah?', ['<?php', '<script php>', '<%php%>']],
                'assignment' => 'Tugas: Kalkulator Sederhana',
            ],
            [
                'title' => 'REST API dengan Laravel Sanctum',
                'about' => 'Membangun API yang aman: resource controller, API resource, autentikasi token, dan pembatasan laju permintaan.',
                'category' => 'backend', 'instructor' => 'budi',
                'modules' => [
                    ['Bab 1: Fondasi API', ['Routing dan Resource Controller', 'API Resource dan Transformasi Data']],
                    ['Bab 2: Keamanan', ['Autentikasi Token dengan Sanctum', 'Rate Limiting dan Validasi']],
                ],
                'quiz' => ['Kuis: Konsep REST', 'Metode HTTP untuk memperbarui sebagian data adalah?', ['PATCH', 'GET', 'DELETE']],
                'assignment' => 'Tugas: API Katalog Buku',
            ],
            [
                'title' => 'React untuk Pemula',
                'about' => 'Komponen, props, state, dan hooks. Membangun antarmuka interaktif dengan React dari dasar.',
                'category' => 'frontend', 'instructor' => 'sari',
                'modules' => [
                    ['Bab 1: Komponen', ['JSX dan Komponen Pertama', 'Props dan Komposisi']],
                    ['Bab 2: State dan Hooks', ['useState untuk Data Berubah', 'useEffect dan Siklus Hidup']],
                ],
                'quiz' => ['Kuis: Dasar React', 'Hook untuk menyimpan state lokal komponen adalah?', ['useState', 'useFetch', 'useRender']],
                'assignment' => null,
            ],
            [
                'title' => 'Tailwind CSS: Desain Cepat dan Konsisten',
                'about' => 'Pendekatan utility-first, sistem spacing, desain responsif, mode gelap, dan menyusun komponen yang rapi.',
                'category' => 'frontend', 'instructor' => 'sari',
                'modules' => [
                    ['Bab 1: Fondasi', ['Filosofi Utility-First', 'Spacing, Warna, dan Tipografi']],
                    ['Bab 2: Tata Letak', ['Flexbox dan Grid', 'Responsif dan Mode Gelap']],
                ],
                'quiz' => ['Kuis: Utility Tailwind', 'Kelas untuk padding horizontal berukuran 4 adalah?', ['px-4', 'p-x4', 'padding-x-4']],
                'assignment' => null,
            ],
            [
                'title' => 'Flutter: Aplikasi Mobile Lintas Platform',
                'about' => 'Satu basis kode untuk Android dan iOS. Widget, tata letak, navigasi, dan pengelolaan state.',
                'category' => 'mobile', 'instructor' => 'rizky',
                'modules' => [
                    ['Bab 1: Dasar Flutter', ['Widget dan Tree', 'Tata Letak Row, Column, Stack']],
                    ['Bab 2: Aplikasi Nyata', ['Navigasi Antar Halaman', 'Mengelola State dengan Provider']],
                ],
                'quiz' => ['Kuis: Widget Flutter', 'Widget yang dapat berubah tampilannya disebut?', ['StatefulWidget', 'StaticWidget', 'FixedWidget']],
                'assignment' => 'Tugas: Aplikasi Catatan',
            ],
            [
                'title' => 'Analisis Data dengan Python dan Pandas',
                'about' => 'Membersihkan, mengolah, dan memvisualisasikan data menggunakan Pandas, NumPy, dan Matplotlib.',
                'category' => 'data-science', 'instructor' => 'dewi',
                'modules' => [
                    ['Bab 1: Fondasi Data', ['Mengenal DataFrame', 'Membaca CSV dan Excel']],
                    ['Bab 2: Pengolahan', ['Membersihkan Data Kotor', 'Agregasi dan Group By', 'Visualisasi Dasar']],
                ],
                'quiz' => ['Kuis: Pandas Dasar', 'Fungsi membaca berkas CSV di Pandas adalah?', ['pd.read_csv()', 'pd.open_csv()', 'pd.load_csv()']],
                'assignment' => 'Tugas: Analisis Data Penjualan',
            ],
            [
                'title' => 'Docker untuk Developer',
                'about' => 'Menjalankan aplikasi secara konsisten di mana pun: image, container, volume, jaringan, dan Docker Compose.',
                'category' => 'devops', 'instructor' => 'agus',
                'modules' => [
                    ['Bab 1: Konsep Dasar', ['Image dan Container', 'Menulis Dockerfile']],
                    ['Bab 2: Multi Layanan', ['Volume dan Jaringan', 'Menyusun Docker Compose']],
                ],
                'quiz' => ['Kuis: Dasar Docker', 'Perintah membangun image dari Dockerfile adalah?', ['docker build', 'docker make', 'docker create']],
                'assignment' => null,
            ],
            [
                'title' => 'Dasar UI/UX: Riset sampai Prototipe',
                'about' => 'Memahami pengguna, menyusun alur, membuat wireframe, dan menguji prototipe sebelum menulis kode.',
                'category' => 'ui-ux', 'instructor' => 'sari',
                'modules' => [
                    ['Bab 1: Memahami Pengguna', ['Riset Pengguna dan Persona', 'Menyusun User Flow']],
                    ['Bab 2: Merancang', ['Wireframe dan Hierarki Visual', 'Prototipe dan Uji Kegunaan']],
                ],
                'quiz' => ['Kuis: Konsep UX', 'Dokumen yang menggambarkan langkah pengguna mencapai tujuan disebut?', ['User flow', 'Style guide', 'Changelog']],
                'assignment' => 'Tugas: Wireframe Aplikasi Kasir',
            ],
        ];

        return collect($definitions)->map(function (array $def) use ($categories, $instructors) {
            $course = Course::create([
                'instructor_id' => $instructors[$def['instructor']]->id,
                'category_id' => $categories[$def['category']]->id,
                'title' => $def['title'],
                // Slug ditulis eksplisit: DatabaseSeeder memakai WithoutModelEvents,
                // sehingga hook penghasil slug pada model tidak ikut berjalan.
                'slug' => Str::slug($def['title']),
                'about' => '<p>'.$def['about'].'</p>',
                'price' => 0,
                'status' => 'published',
            ]);

            $firstModule = null;

            foreach ($def['modules'] as $order => [$moduleTitle, $lessons]) {
                $module = Module::create([
                    'course_id' => $course->id,
                    'title' => $moduleTitle,
                    'order' => $order + 1,
                ]);
                $firstModule ??= $module;

                foreach ($lessons as $lessonOrder => $lessonTitle) {
                    Lesson::create([
                        'module_id' => $module->id,
                        'title' => $lessonTitle,
                        'slug' => Str::slug($lessonTitle),
                        'content' => '<h2>'.$lessonTitle.'</h2><p>Materi ini membahas '
                            .lcfirst($lessonTitle).' secara bertahap, lengkap dengan contoh penerapannya.</p>',
                        'is_free_preview' => $lessonOrder === 0,
                        'order' => $lessonOrder + 1,
                    ]);
                }
            }

            [$quizTitle, $question, $options] = $def['quiz'];
            $quiz = Quiz::create([
                'module_id' => $firstModule->id,
                'title' => $quizTitle,
                'passing_score' => 70,
            ]);
            $q = Question::create(['quiz_id' => $quiz->id, 'question' => $question, 'order' => 1]);
            foreach ($options as $i => $optionText) {
                QuestionOption::create([
                    'question_id' => $q->id,
                    'option_text' => $optionText,
                    'is_correct' => $i === 0,
                ]);
            }

            if ($def['assignment']) {
                Assignment::create([
                    'module_id' => $firstModule->id,
                    'title' => $def['assignment'],
                    'description' => '<p>Kerjakan sesuai instruksi pada materi. Kumpulkan berkas atau tuliskan jawabanmu pada kolom yang tersedia.</p>',
                    'due_date' => now()->addDays(21),
                    'max_score' => 100,
                    'passing_score' => 60,
                ]);
            }

            return $course;
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function seedStudents(Role $role): Collection
    {
        $names = [
            'Ahmad Fauzi', 'Siti Nurhaliza', 'Bagus Wicaksono', 'Rina Kartika',
            'Doni Saputra', 'Maya Lestari', 'Fajar Ramadhan', 'Intan Permata',
            'Yoga Prasetyo',
        ];

        return collect($names)->map(function (string $name, int $i) use ($role) {
            $user = User::firstOrCreate(
                ['email' => 'siswa'.($i + 1).'@lms.com'],
                ['name' => $name, 'password' => bcrypt('password123'), 'email_verified_at' => now()]
            );
            $user->assignRole($role);

            return $user;
        });
    }

    /**
     * Daftarkan siswa dengan tingkat kemajuan berbeda-beda.
     *
     * Progres dihitung dari materi yang benar-benar ditandai selesai, bukan
     * ditulis sebagai angka — sehingga poin dan badge ikut terbentuk wajar.
     */
    private function seedEnrollments(Collection $courses, Collection $students): void
    {
        foreach ($students as $index => $student) {
            // Tiap siswa mengambil 3 kursus berbeda, digilir agar sebarannya merata.
            // values() penting: slice() mempertahankan kunci asli, sehingga tanpa
            // ini $position di bawah bukan 0/1/2 dan porsi progresnya meleset.
            $taken = $courses->slice($index % max(1, $courses->count() - 2), 3)->values();

            foreach ($taken as $position => $course) {
                $enrollment = Enrollment::firstOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id],
                    ['amount_paid' => 0, 'status' => 'active', 'progress_percentage' => 0]
                );

                // Kursus pertama dituntaskan, sisanya sebagian saja
                $portion = match ($position) {
                    0 => 1.0,
                    1 => 0.5,
                    default => 0.2,
                };

                $this->advance($student, $course, $enrollment, $portion);
            }
        }
    }

    /**
     * Selesaikan sebagian materi (dan kuisnya bila dituntaskan penuh).
     */
    private function advance(User $student, Course $course, Enrollment $enrollment, float $portion): void
    {
        $lessons = $course->lessons()->get();
        $take = (int) ceil($lessons->count() * $portion);

        foreach ($lessons->take($take) as $lesson) {
            $student->completedLessons()->syncWithoutDetaching([
                $lesson->id => ['completed_at' => now()],
            ]);
        }

        if ($portion >= 1.0) {
            $moduleIds = $course->modules->pluck('id');

            foreach (Quiz::whereIn('module_id', $moduleIds)->get() as $quiz) {
                QuizAttempt::firstOrCreate(
                    ['user_id' => $student->id, 'quiz_id' => $quiz->id],
                    [
                        'score' => 100,
                        'passed' => true,
                        'answers' => [],
                        'started_at' => now()->subMinutes(10),
                        'completed_at' => now()->subMinutes(5),
                        'graded_at' => now()->subMinutes(5),
                    ]
                );
            }

            // Tugas ikut dihitung sebagai unit progres, jadi kursus yang
            // memilikinya tidak akan pernah mencapai 100% tanpa pengumpulan
            // yang sudah dinilai lulus.
            foreach (Assignment::whereIn('module_id', $moduleIds)->get() as $assignment) {
                $assignment->submissions()->firstOrCreate(
                    ['user_id' => $student->id],
                    [
                        'content' => 'Tugas dikerjakan sesuai instruksi pada materi.',
                        'submitted_at' => now()->subDays(2),
                        'score' => 85,
                        'feedback' => 'Sudah tepat. Pertahankan.',
                        'graded_at' => now()->subDay(),
                    ]
                );
            }
        }

        $enrollment->recalculateProgress();
    }
}
