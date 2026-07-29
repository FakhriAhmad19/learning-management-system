<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $categorySlug = $request->query('kategori');

        // Katalog kursus terpublikasi, bisa disaring lewat pencarian & kategori
        $courses = Course::where('status', 'published')
            ->with(['instructor', 'modules', 'category'])
            ->withCount('enrollments')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('about', 'like', "%{$search}%");
                });
            })
            ->when($categorySlug, function ($query) use ($categorySlug) {
                $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
            })
            ->latest()
            ->get();

        // Hanya tampilkan kategori yang punya kursus terpublikasi
        $categories = Category::whereHas('courses', fn ($q) => $q->where('status', 'published'))
            ->orderBy('name')
            ->get();

        $activeCategory = $categorySlug
            ? $categories->firstWhere('slug', $categorySlug)
            : null;

        return view('welcome', compact('courses', 'categories', 'search', 'activeCategory'));
    }

    public function show($slug)
    {
        // Halaman Detail Kursus
        $course = Course::where('slug', $slug)
            ->where('status', 'published')
            ->with(['instructor', 'modules.lessons'])
            ->firstOrFail();

        // Status pendaftaran siswa yang sedang login (untuk state tombol Enroll)
        $enrollment = Auth::check()
            ? $course->enrollments()->where('user_id', Auth::id())->first()
            : null;

        return view('courses.show', compact('course', 'enrollment'));
    }

    /**
     * Dashboard siswa: daftar kursus yang sedang/selesai diikuti beserta progresnya.
     */
    public function myCourses()
    {
        $enrollments = Auth::user()->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with(['course.instructor', 'course.modules'])
            ->latest()
            ->get();

        return view('my-courses', compact('enrollments'));
    }
}
