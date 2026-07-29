<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id',
        'title',
        'order',
    ];

    /**
     * Relasi: Modul ini milik satu Kursus tertentu
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Relasi: Satu modul memiliki banyak Lesson (Pelajaran)
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order', 'asc');
    }

    /**
     * Relasi: Satu modul boleh memiliki satu Kuis akhir bab
     */
    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    /**
     * Relasi: Satu modul boleh memiliki beberapa Tugas
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
