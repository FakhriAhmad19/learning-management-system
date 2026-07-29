<?php

namespace App\Gamification\Badges;

use App\Models\User;

/**
 * Syarat badge ditulis sebagai kode, bukan data, karena sebagian syaratnya
 * (mis. "lulus 5 kuis berbeda") butuh logika, bukan sekadar ambang angka.
 * Menambah badge baru = menambah satu kelas di folder ini lalu mendaftarkannya
 * di App\Gamification\BadgeRegistry.
 */
interface Badge
{
    /** Kunci stabil yang disimpan di kolom user_badges.badge. */
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /** Emoji yang ditampilkan pada kartu badge. */
    public function icon(): string;

    public function isEarnedBy(User $user): bool;
}
