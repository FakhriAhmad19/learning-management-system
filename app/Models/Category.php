<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * Slug kategori dipakai di query string katalog (?kategori=...), jadi harus unik.
     */
    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            $base = Str::slug($category->slug ?: $category->name);
            $slug = $base;
            $i = 2;

            while (static::where('slug', $slug)
                ->when($category->exists, fn ($q) => $q->whereKeyNot($category->getKey()))
                ->exists()
            ) {
                $slug = "{$base}-{$i}";
                $i++;
            }

            $category->slug = $slug;
        });
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
