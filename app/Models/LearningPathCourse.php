<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris pivot urutan kursus dalam jalur. Dibuat sebagai model tersendiri agar
 * urutannya bisa diatur lewat repeater yang dapat di-drag di panel Filament.
 */
class LearningPathCourse extends Model
{
    protected $table = 'learning_path_course';

    protected $fillable = [
        'learning_path_id',
        'course_id',
        'order',
    ];

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
