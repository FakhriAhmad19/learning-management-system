<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(string $title, string $about = 'deskripsi', ?Category $category = null, string $status = 'published'): Course
    {
        return Course::create([
            'instructor_id' => User::factory()->create()->id,
            'category_id' => $category?->id,
            'title' => $title,
            'about' => $about,
            'price' => 0,
            'status' => $status,
        ]);
    }

    public function test_catalog_lists_only_published_courses(): void
    {
        $this->makeCourse('Kursus Terbit');
        $this->makeCourse('Kursus Draft', status: 'draft');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kursus Terbit')
            ->assertDontSee('Kursus Draft');
    }

    public function test_search_matches_title(): void
    {
        $this->makeCourse('Laravel Dasar');
        $this->makeCourse('Vue.js Lanjutan');

        $this->get(route('home', ['q' => 'Laravel']))
            ->assertOk()
            ->assertSee('Laravel Dasar')
            ->assertDontSee('Vue.js Lanjutan');
    }

    public function test_search_matches_description(): void
    {
        $this->makeCourse('Kursus A', about: 'membahas Eloquent ORM secara mendalam');
        $this->makeCourse('Kursus B', about: 'membahas CSS');

        $this->get(route('home', ['q' => 'Eloquent']))
            ->assertOk()
            ->assertSee('Kursus A')
            ->assertDontSee('Kursus B');
    }

    public function test_category_filter_narrows_results(): void
    {
        $backend = Category::create(['name' => 'Backend']);
        $frontend = Category::create(['name' => 'Frontend']);

        $this->makeCourse('Kursus Backend', category: $backend);
        $this->makeCourse('Kursus Frontend', category: $frontend);

        $this->get(route('home', ['kategori' => $backend->slug]))
            ->assertOk()
            ->assertSee('Kursus Backend')
            ->assertDontSee('Kursus Frontend');
    }

    public function test_search_and_category_filter_combine(): void
    {
        $backend = Category::create(['name' => 'Backend']);
        $frontend = Category::create(['name' => 'Frontend']);

        $this->makeCourse('Laravel Dasar', category: $backend);
        $this->makeCourse('Laravel di Frontend', category: $frontend);

        $this->get(route('home', ['q' => 'Laravel', 'kategori' => $backend->slug]))
            ->assertOk()
            ->assertSee('Laravel Dasar')
            ->assertDontSee('Laravel di Frontend');
    }

    public function test_empty_search_shows_helpful_message(): void
    {
        $this->makeCourse('Laravel Dasar');

        $this->get(route('home', ['q' => 'tidak-ada-kursus-ini']))
            ->assertOk()
            ->assertSee('Tidak ada kursus yang cocok');
    }

    public function test_category_slug_is_generated_and_unique(): void
    {
        $first = Category::create(['name' => 'Data Science']);
        $second = Category::create(['name' => 'Data Science']);

        $this->assertSame('data-science', $first->slug);
        $this->assertSame('data-science-2', $second->slug);
    }

    public function test_only_categories_with_published_courses_are_shown(): void
    {
        $used = Category::create(['name' => 'Backend']);
        Category::create(['name' => 'Kategori Kosong']);

        $this->makeCourse('Kursus Backend', category: $used);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Backend')
            ->assertDontSee('Kategori Kosong');
    }
}
