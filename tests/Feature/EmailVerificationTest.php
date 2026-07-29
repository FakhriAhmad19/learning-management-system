<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email_and_user_is_unverified(): void
    {
        Notification::fake();
        Role::firstOrCreate(['name' => 'Student']);

        $this->post('/register', [
            'name' => 'Calon Siswa',
            'email' => 'calon@test.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $user = User::where('email', 'calon@test.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_is_redirected_from_learning_features(): void
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->unverified()->create();
        $user->assignRole('Student');

        $this->actingAs($user)->get('/my-courses')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_access_learning_features(): void
    {
        Role::firstOrCreate(['name' => 'Student']);
        $user = User::factory()->create(); // factory menandai email terverifikasi
        $user->assignRole('Student');

        $this->actingAs($user)->get('/my-courses')->assertOk();
    }

    public function test_verification_notice_page_renders(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('verification.notice'))
            ->assertOk()
            ->assertSee('Verifikasi');
    }

    public function test_signed_url_verifies_the_email(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
