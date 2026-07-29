<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('app/backups');
        File::ensureDirectoryExists($this->directory);
        $this->clearBackups();
    }

    protected function tearDown(): void
    {
        $this->clearBackups();

        parent::tearDown();
    }

    private function clearBackups(): void
    {
        foreach (File::glob($this->directory.'/*.sql.gz') as $file) {
            File::delete($file);
        }
    }

    /**
     * @return array<int, string>
     */
    private function backups(): array
    {
        return File::glob($this->directory.'/*.sql.gz');
    }

    public function test_creates_a_gzipped_dump_containing_the_schema(): void
    {
        $this->artisan('backup:database')->assertSuccessful();

        $files = $this->backups();
        $this->assertCount(1, $files);

        // Isi dump harus benar-benar bisa dibaca ulang, bukan sekadar ada
        $contents = gzdecode(File::get($files[0]));
        $this->assertIsString($contents);
        $this->assertStringContainsString('CREATE TABLE', $contents);
        $this->assertStringContainsString('users', $contents);
    }

    public function test_backup_filename_contains_database_name_and_timestamp(): void
    {
        $this->artisan('backup:database')->assertSuccessful();

        $name = basename($this->backups()[0]);
        $database = config('database.connections.'.config('database.default').'.database');

        $this->assertStringStartsWith($database.'-', $name);
        $this->assertMatchesRegularExpression('/-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/', $name);
    }

    public function test_old_backups_are_removed(): void
    {
        $old = $this->directory.'/lama-2020-01-01-000000.sql.gz';
        File::put($old, 'isi lama');
        touch($old, now()->subDays(30)->getTimestamp());

        $this->artisan('backup:database', ['--keep' => 7])->assertSuccessful();

        $this->assertFileDoesNotExist($old);
    }

    public function test_recent_backups_are_kept(): void
    {
        $recent = $this->directory.'/baru-2026-07-28-000000.sql.gz';
        File::put($recent, 'isi baru');
        touch($recent, now()->subDay()->getTimestamp());

        $this->artisan('backup:database', ['--keep' => 7])->assertSuccessful();

        $this->assertFileExists($recent);
    }

    public function test_keep_zero_disables_rotation(): void
    {
        $old = $this->directory.'/lama-2020-01-01-000000.sql.gz';
        File::put($old, 'isi lama');
        touch($old, now()->subDays(365)->getTimestamp());

        $this->artisan('backup:database', ['--keep' => 0])->assertSuccessful();

        $this->assertFileExists($old);
    }

    /**
     * Kegagalan harus terlihat sebagai kegagalan — bukan menghasilkan berkas
     * kosong yang tampak seperti cadangan sah.
     */
    public function test_fails_and_leaves_no_file_when_credentials_are_wrong(): void
    {
        config()->set(
            'database.connections.'.config('database.default').'.password',
            'password-yang-salah'
        );

        $this->artisan('backup:database')->assertFailed();

        $this->assertCount(0, $this->backups());
    }

    public function test_rejects_non_mysql_connections(): void
    {
        $original = config('database.default');

        // Koneksi default WAJIB dikembalikan sebelum test selesai: RefreshDatabase
        // membatalkan transaksinya pada koneksi default saat teardown, sehingga
        // meninggalkannya sebagai sqlite membuat teardown gagal mencari berkas
        // SQLite yang tidak ada.
        try {
            config()->set('database.default', 'sqlite');

            $this->artisan('backup:database')->assertFailed();
        } finally {
            config()->set('database.default', $original);
        }
    }
}
