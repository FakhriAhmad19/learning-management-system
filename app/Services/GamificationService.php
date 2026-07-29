<?php

namespace App\Services;

use App\Gamification\BadgeRegistry;
use App\Gamification\Badges\Badge;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\PointAward;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Models\UserBadge;
use App\Notifications\BadgeEarned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Poin & badge dihitung dengan cara **rekonsiliasi**, bukan penambahan.
 *
 * Setiap sinkronisasi memeriksa keadaan siswa saat ini lalu memberikan
 * poin yang belum pernah diberikan. Kunci unik pada `point_awards` membuat
 * pengulangan aman, sehingga tidak ada poin ganda meski sync dipanggil
 * berkali-kali dari jalur yang berbeda.
 */
class GamificationService
{
    public function __construct(private BadgeRegistry $badges) {}

    /**
     * Selaraskan poin untuk satu pendaftaran, lalu evaluasi badge.
     */
    public function sync(Enrollment $enrollment): void
    {
        $user = $enrollment->student;
        $course = $enrollment->course;

        if ($user === null || $course === null) {
            return;
        }

        $this->syncLessonPoints($user, $course);
        $this->syncQuizPoints($user, $course);
        $this->syncAssignmentPoints($user, $course);
        $this->syncCoursePoints($user, $course, $enrollment);

        $this->syncBadges($user);
    }

    private function syncLessonPoints(User $user, Course $course): void
    {
        $lessonIds = $this->lessonIds($course);

        $completed = $user->completedLessons()
            ->whereIn('lessons.id', $lessonIds)
            ->pluck('lessons.id');

        foreach ($completed as $lessonId) {
            $this->award($user, $course, PointAward::TYPE_LESSON, Lesson::class, $lessonId);
        }
    }

    private function syncQuizPoints(User $user, Course $course): void
    {
        $quizIds = Quiz::whereIn('module_id', $this->moduleIds($course))->pluck('id');

        $passed = QuizAttempt::where('user_id', $user->id)
            ->whereIn('quiz_id', $quizIds)
            ->where('passed', true)
            ->pluck('quiz_id')
            ->unique();

        foreach ($passed as $quizId) {
            $this->award($user, $course, PointAward::TYPE_QUIZ, Quiz::class, $quizId);
        }
    }

    private function syncAssignmentPoints(User $user, Course $course): void
    {
        $assignmentIds = Assignment::whereIn('module_id', $this->moduleIds($course))->pluck('id');

        // Hanya tugas yang sudah dinilai DAN mencapai batas lulus
        $passed = AssignmentSubmission::query()
            ->join('assignments', 'assignments.id', '=', 'assignment_submissions.assignment_id')
            ->where('assignment_submissions.user_id', $user->id)
            ->whereIn('assignment_submissions.assignment_id', $assignmentIds)
            ->whereNotNull('assignment_submissions.graded_at')
            ->whereColumn('assignment_submissions.score', '>=', 'assignments.passing_score')
            ->pluck('assignment_submissions.assignment_id');

        foreach ($passed as $assignmentId) {
            $this->award($user, $course, PointAward::TYPE_ASSIGNMENT, Assignment::class, $assignmentId);
        }
    }

    private function syncCoursePoints(User $user, Course $course, Enrollment $enrollment): void
    {
        if ($enrollment->status !== 'completed') {
            return;
        }

        $this->award($user, $course, PointAward::TYPE_COURSE, Course::class, $course->id);
    }

    /**
     * Catat poin bila sumber ini belum pernah menghasilkan poin.
     */
    private function award(User $user, Course $course, string $type, string $awardableType, int $awardableId): void
    {
        PointAward::firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $type,
                'awardable_type' => $awardableType,
                'awardable_id' => $awardableId,
            ],
            [
                'course_id' => $course->id,
                'points' => PointAward::pointsFor($type),
            ]
        );
    }

    /**
     * Berikan badge yang syaratnya sudah terpenuhi dan belum pernah diraih.
     */
    public function syncBadges(User $user): void
    {
        $earned = UserBadge::where('user_id', $user->id)->pluck('badge')->all();

        $this->badges->all()
            ->reject(fn (Badge $badge) => in_array($badge->key(), $earned, true))
            ->filter(fn (Badge $badge) => $badge->isEarnedBy($user))
            ->each(function (Badge $badge) use ($user) {
                $record = UserBadge::firstOrCreate([
                    'user_id' => $user->id,
                    'badge' => $badge->key(),
                ]);

                if ($record->wasRecentlyCreated) {
                    $user->notify(new BadgeEarned($badge));
                }
            });
    }

    /**
     * Total poin seorang siswa di seluruh kursus.
     */
    public function totalPoints(User $user): int
    {
        return (int) PointAward::where('user_id', $user->id)->sum('points');
    }

    /**
     * Poin seorang siswa pada satu kursus.
     */
    public function pointsInCourse(User $user, Course $course): int
    {
        return (int) PointAward::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->sum('points');
    }

    /**
     * Papan peringkat sebuah kursus: hanya peserta kursus tersebut.
     *
     * @return Collection<int, array{user: User, points: int, rank: int}>
     */
    public function leaderboard(Course $course, int $limit = 20): Collection
    {
        $participantIds = Enrollment::where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->pluck('user_id');

        $totals = PointAward::where('course_id', $course->id)
            ->whereIn('user_id', $participantIds)
            ->groupBy('user_id')
            ->select('user_id', DB::raw('SUM(points) as total'))
            ->orderByDesc('total')
            ->orderBy('user_id')
            ->limit($limit)
            ->get();

        $users = User::whereIn('id', $totals->pluck('user_id'))->get()->keyBy('id');

        return $totals->values()->map(fn ($row, int $index) => [
            'user' => $users->get($row->user_id),
            'points' => (int) $row->total,
            'rank' => $index + 1,
        ]);
    }

    /**
     * Peringkat seorang siswa di sebuah kursus (1 = teratas), null bila belum berpoin.
     */
    public function rankInCourse(User $user, Course $course): ?int
    {
        $points = $this->pointsInCourse($user, $course);

        if ($points === 0) {
            return null;
        }

        $ahead = PointAward::where('course_id', $course->id)
            ->groupBy('user_id')
            ->havingRaw('SUM(points) > ?', [$points])
            ->select('user_id')
            ->get()
            ->count();

        return $ahead + 1;
    }

    /**
     * @return Collection<int, int>
     */
    private function moduleIds(Course $course): Collection
    {
        return Module::where('course_id', $course->id)->pluck('id');
    }

    /**
     * @return Collection<int, int>
     */
    private function lessonIds(Course $course): Collection
    {
        return Lesson::whereIn('module_id', $this->moduleIds($course))->pluck('id');
    }
}
