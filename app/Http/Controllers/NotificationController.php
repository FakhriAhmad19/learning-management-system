<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Daftar notifikasi siswa (terbaru di atas).
     */
    public function index()
    {
        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->limit(50)
            ->get();

        return view('notifications.index', compact('notifications'));
    }

    /**
     * Tandai satu notifikasi dibaca lalu arahkan ke halaman tujuannya.
     */
    public function read(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('notifications.index'));
    }

    /**
     * Tandai seluruh notifikasi sebagai sudah dibaca.
     */
    public function readAll(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
