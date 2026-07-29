<?php

namespace App\Http\Controllers;

use App\Gamification\BadgeRegistry;
use App\Gamification\Badges\Badge;
use App\Models\Course;
use App\Models\PointAward;
use App\Models\UserBadge;
use App\Services\GamificationService;
use Illuminate\Support\Facades\Auth;

class AchievementController extends Controller
{
    public function __construct(
        private GamificationService $gamification,
        private BadgeRegistry $badges,
    ) {}

    /**
     * "Pencapaian Saya": total poin, rincian per kursus, dan koleksi badge.
     */
    public function index()
    {
        $user = Auth::user();

        $totalPoints = $this->gamification->totalPoints($user);

        // Poin per kursus yang diikuti, beserta peringkat siswa di kursus itu
        $courses = $user->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->get()
            ->map(fn ($enrollment) => [
                'course' => $enrollment->course,
                'points' => $this->gamification->pointsInCourse($user, $enrollment->course),
                'rank' => $this->gamification->rankInCourse($user, $enrollment->course),
            ])
            ->sortByDesc('points')
            ->values();

        $earnedKeys = UserBadge::where('user_id', $user->id)->pluck('created_at', 'badge');

        $badges = $this->badges->all()->map(fn (Badge $badge) => [
            'badge' => $badge,
            'earned_at' => $earnedKeys->get($badge->key()),
        ]);

        $recent = PointAward::where('user_id', $user->id)
            ->with('course')
            ->latest()
            ->limit(15)
            ->get();

        return view('achievements.index', compact('totalPoints', 'courses', 'badges', 'recent'));
    }

    /**
     * Papan peringkat sebuah kursus — hanya untuk peserta kursus tersebut.
     */
    public function leaderboard(Course $course)
    {
        $user = Auth::user();

        $isParticipant = $course->enrollments()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        // Pengajar kelas boleh melihat papan peringkat kelasnya
        if (! $isParticipant && $course->instructor_id !== $user->id) {
            return redirect()
                ->route('courses.show', $course->slug)
                ->with('error', 'Kamu belum terdaftar di kelas ini.');
        }

        return view('achievements.leaderboard', [
            'course' => $course,
            'entries' => $this->gamification->leaderboard($course),
            'myPoints' => $this->gamification->pointsInCourse($user, $course),
            'myRank' => $this->gamification->rankInCourse($user, $course),
        ]);
    }
}
