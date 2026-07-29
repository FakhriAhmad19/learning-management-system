<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Lesson extends Model
{
    use HasFactory;

    /**
     * Pastikan slug materi terisi & unik dalam satu kursus
     * (dipakai untuk navigasi Ruang Belajar /learn/{course}/{lesson}).
     */
    protected static function booted(): void
    {
        static::saving(function (Lesson $lesson): void {
            $base = Str::slug($lesson->slug ?: $lesson->title);
            $slug = $base;
            $i = 2;

            $courseId = Module::whereKey($lesson->module_id)->value('course_id');

            while (
                static::where('slug', $slug)
                    ->whereHas('module', fn ($q) => $q->where('course_id', $courseId))
                    ->when($lesson->exists, fn ($q) => $q->whereKeyNot($lesson->getKey()))
                    ->exists()
            ) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $lesson->slug = $slug;
        });
    }

    protected $fillable = [
        'module_id',
        'title',
        'slug',
        'content',
        'attachment',
        'is_free_preview',
        'order',
    ];

    protected $casts = [
        'is_free_preview' => 'boolean',
    ];

    /**
     * Relasi: Pelajaran ini berada di dalam satu Modul tertentu
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Relasi: Tanya-jawab pada pelajaran ini (pertanyaan beserta balasannya)
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }
}
