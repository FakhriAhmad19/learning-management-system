<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Halaman profil / akun siswa.
     * Update nama/email & password diproses oleh route bawaan Laravel Fortify
     * (PUT /user/profile-information dan PUT /user/password).
     */
    public function edit()
    {
        return view('profile.edit', ['user' => Auth::user()]);
    }
}
