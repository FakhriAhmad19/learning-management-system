<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    /**
     * Pastikan slug selalu terisi & unik secara global (dipakai di URL /courses/{slug}).
     */
    protected static function booted(): void
    {
        static::saving(function (Course $course): void {
            $base = Str::slug($course->slug ?: $course->title);
            $slug = $base;
            $i = 2;

            while (static::where('slug', $slug)
                ->when($course->exists, fn ($q) => $q->whereKeyNot($course->getKey()))
                ->exists()
            ) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $course->slug = $slug;
        });
    }

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'about',
        'thumbnail',
        'price',
        'status',
    ];

    /**
     * Relasi: Kursus ini dimiliki oleh satu Pengajar (User dengan Role Instructor)
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /**
     * Relasi: Kursus ini masuk dalam satu Kategori (opsional)
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi: Kursus ini bisa menjadi bagian dari beberapa Jalur Belajar
     */
    public function learningPaths(): BelongsToMany
    {
        return $this->belongsToMany(LearningPath::class, 'learning_path_course')
            ->withPivot('order')
            ->withTimestamps();
    }

    /**
     * Relasi: Satu kursus memiliki banyak Modul (Bab)
     */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order', 'asc');
    }

    /**
     * Relasi: Satu kursus memiliki banyak Pendaftaran (Enrollments)
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Relasi Lanjutan (HasManyThrough):
     * Mengambil seluruh Lesson (Pelajaran) di dalam kursus ini melalui relasi Module
     */
    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Module::class);
    }
}
