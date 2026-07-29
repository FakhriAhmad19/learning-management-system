<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_confirm_and_disable_two_factor(): void
    {
        $user = User::factory()->create();

        // 1. Aktifkan -> secret terbuat, tapi belum dikonfirmasi
        $this->actingAs($user)->post(route('two-factor.enable'));
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_confirmed_at);

        // Halaman profil menampilkan status menunggu konfirmasi + QR
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Menunggu Konfirmasi')
            ->assertSee('<svg', false);

        // 2. Konfirmasi dengan OTP valid
        $secret = decrypt($user->two_factor_secret);
        $otp = app(Google2FA::class)->getCurrentOtp($secret);
        $this->actingAs($user)->post(route('two-factor.confirm'), ['code' => $otp]);
        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);

        // Profil menampilkan opsi nonaktifkan (2FA aktif)
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Nonaktifkan 2FA');

        // 3. Nonaktifkan -> secret dihapus
        $this->actingAs($user)->delete(route('two-factor.disable'));
        $user->refresh();
        $this->assertNull($user->two_factor_secret);
    }

    public function test_profile_shows_enable_button_when_2fa_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Aktifkan 2FA');
    }
}
