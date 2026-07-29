<?php

namespace App\Gamification;

use App\Gamification\Badges\Badge;
use App\Gamification\Badges\Collector;
use App\Gamification\Badges\DiligentLearner;
use App\Gamification\Badges\FirstStep;
use App\Gamification\Badges\Graduate;
use App\Gamification\Badges\OnTime;
use App\Gamification\Badges\PerfectScore;
use App\Gamification\Badges\QuizMaster;
use Illuminate\Support\Collection;

/**
 * Daftar seluruh badge yang ada. Urutannya menentukan urutan tampil
 * di halaman Pencapaian — dari yang paling mudah ke paling sulit.
 */
class BadgeRegistry
{
    /** @var array<int, class-string<Badge>> */
    private const BADGES = [
        FirstStep::class,
        DiligentLearner::class,
        QuizMaster::class,
        PerfectScore::class,
        OnTime::class,
        Graduate::class,
        Collector::class,
    ];

    /**
     * @return Collection<int, Badge>
     */
    public function all(): Collection
    {
        return collect(self::BADGES)->map(fn (string $class) => app($class));
    }

    public function find(string $key): ?Badge
    {
        return $this->all()->firstWhere(fn (Badge $badge) => $badge->key() === $key);
    }
}
