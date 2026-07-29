<?php

namespace App\Models;

use App\Notifications\DiscussionReplied;
use App\Notifications\QuestionAsked;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discussion extends Model
{
    use HasFactory;

    protected $fillable = [
        'lesson_id',
        'user_id',
        'parent_id',
        'body',
    ];

    protected static function booted(): void
    {
        static::created(function (Discussion $discussion): void {
            if ($discussion->isReply()) {
                // Penanya diberi tahu saat ada yang membalas — kecuali membalas dirinya sendiri
                $author = $discussion->parent?->author;

                if ($author && $author->id !== $discussion->user_id) {
                    $author->notify(new DiscussionReplied($discussion));
                }

                return;
            }

            // Pertanyaan baru: kabari pengajar kelas (kecuali ia sendiri yang bertanya)
            $instructor = $discussion->lesson?->module?->course?->instructor;

            if ($instructor && $instructor->id !== $discussion->user_id) {
                $instructor->notify(new QuestionAsked($discussion));
            }
        });
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Discussion::class, 'parent_id')->oldest();
    }

    public function isReply(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Jawaban dari pengajar kelas ditandai khusus agar mudah dikenali siswa.
     */
    public function isFromInstructor(): bool
    {
        return $this->user_id === $this->lesson?->module?->course?->instructor_id;
    }
}
