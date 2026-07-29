<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'status',
    ];

    /**
     * Slug dipakai di URL /paths/{slug}, jadi harus unik.
     */
    protected static function booted(): void
    {
        static::saving(function (LearningPath $path): void {
            $base = Str::slug($path->slug ?: $path->title);
            $slug = $base;
            $i = 2;

            while (static::where('slug', $slug)
                ->when($path->exists, fn ($q) => $q->whereKeyNot($path->getKey()))
                ->exists()
            ) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $path->slug = $slug;
        });
    }

    /**
     * Kursus dalam jalur ini, sesuai urutan yang ditetapkan admin.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'learning_path_course')
            ->withPivot('order')
            ->withTimestamps()
            ->orderBy('learning_path_course.order');
    }

    /**
     * Baris urutan kursus — dipakai form admin agar urutannya bisa digeser.
     */
    public function pathCourses(): HasMany
    {
        return $this->hasMany(LearningPathCourse::class)->orderBy('order');
    }

    /**
     * Siswa yang mengikuti jalur ini.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'learning_path_user')->withTimestamps();
    }

    public function isJoinedBy(User $user): bool
    {
        return $this->students()->whereKey($user->id)->exists();
    }

    /**
     * Kursus terbuka bila SELURUH kursus sebelumnya di jalur ini sudah selesai.
     * Kursus pertama selalu terbuka.
     */
    public function isCourseUnlockedFor(User $user, Course $course): bool
    {
        $courses = $this->courses;
        $position = $courses->search(fn (Course $c) => $c->id === $course->id);

        // Bukan bagian jalur ini — tidak ada yang mengunci
        if ($position === false) {
            return true;
        }

        $prerequisites = $courses->take($position);

        if ($prerequisites->isEmpty()) {
            return true;
        }

        $completedCourseIds = Enrollment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereIn('course_id', $prerequisites->pluck('id'))
            ->pluck('course_id');

        return $completedCourseIds->count() === $prerequisites->count();
    }

    /**
     * Kursus yang harus diselesaikan lebih dulu sebelum kursus ini terbuka.
     *
     * @return Collection<int, Course>
     */
    public function unmetPrerequisitesFor(User $user, Course $course): Collection
    {
        $courses = $this->courses;
        $position = $courses->search(fn (Course $c) => $c->id === $course->id);

        if ($position === false || $position === 0) {
            return collect();
        }

        $completedIds = Enrollment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->pluck('course_id');

        return $courses->take($position)->reject(fn (Course $c) => $completedIds->contains($c->id))->values();
    }

    /**
     * Persentase kursus dalam jalur yang sudah diselesaikan siswa.
     */
    public function progressFor(User $user): int
    {
        $total = $this->courses->count();

        if ($total === 0) {
            return 0;
        }

        $completed = Enrollment::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereIn('course_id', $this->courses->pluck('id'))
            ->count();

        return (int) round($completed / $total * 100);
    }
}
