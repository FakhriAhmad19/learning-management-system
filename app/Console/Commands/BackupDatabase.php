<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Membuat cadangan database ke berkas .sql.gz, lalu membuang cadangan lama.
 *
 * Dijadwalkan harian lewat routes/console.php, tetapi juga aman dijalankan
 * manual kapan saja — mis. sebelum melakukan migrasi berisiko.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database
                            {--keep= : Berapa hari cadangan disimpan (bawaan: BACKUP_KEEP_DAYS atau 7)}';

    protected $description = 'Membuat cadangan database dan menghapus cadangan yang sudah kedaluwarsa';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            $this->error("Hanya mendukung MySQL, koneksi '{$connection}' memakai driver '{$config['driver']}'.");

            return self::FAILURE;
        }

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);

        $target = $directory.'/'.$config['database'].'-'.now()->format('Y-m-d-His').'.sql.gz';

        // Kredensial ditulis ke berkas sementara ber-permission 0600, bukan
        // dilewatkan sebagai argumen — argumen perintah terlihat oleh siapa pun
        // yang menjalankan `ps` di mesin yang sama.
        $credentialsFile = tempnam(sys_get_temp_dir(), 'dbcnf');
        chmod($credentialsFile, 0600);
        file_put_contents($credentialsFile, sprintf(
            "[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%s\n",
            $config['username'],
            addslashes((string) $config['password']),
            $config['host'],
            $config['port'],
        ));

        try {
            $this->info("Mencadangkan database '{$config['database']}'...");

            $process = Process::fromShellCommandline(
                'mysqldump --defaults-extra-file=$CNF '
                .config('backup.mysqldump_options')
                .' $DATABASE | gzip > $TARGET'
            );
            $process->setTimeout(900);
            $process->run(null, [
                'CNF' => $credentialsFile,
                'DATABASE' => $config['database'],
                'TARGET' => $target,
            ]);

            if (! $process->isSuccessful()) {
                // Jangan tinggalkan berkas separuh jadi yang tampak seperti
                // cadangan sah padahal isinya tidak lengkap.
                File::delete($target);
                $this->error('mysqldump gagal: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        } finally {
            File::delete($credentialsFile);
        }

        $size = File::exists($target) ? File::size($target) : 0;

        // Dump yang sah selalu memuat header dan definisi tabel. Ukuran yang
        // terlalu kecil menandakan dump kosong meski mysqldump keluar sukses.
        if ($size < 1024) {
            File::delete($target);
            $this->error('Hasil cadangan terlalu kecil ('.$size.' byte) — dianggap gagal.');

            return self::FAILURE;
        }

        $this->info('Selesai: '.basename($target).' ('.$this->humanSize($size).')');

        // Bandingkan dengan null, bukan pakai ?: — string "0" bernilai falsy
        // di PHP, sehingga --keep=0 akan diam-diam berubah jadi nilai bawaan.
        $keep = $this->option('keep');
        $this->rotate($directory, $keep !== null ? (int) $keep : (int) config('backup.keep_days'));

        return self::SUCCESS;
    }

    /**
     * Hapus cadangan yang lebih tua dari batas simpan.
     */
    private function rotate(string $directory, int $keepDays): void
    {
        if ($keepDays < 1) {
            return;
        }

        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $removed = 0;

        foreach (File::glob($directory.'/*.sql.gz') as $file) {
            if (File::lastModified($file) < $cutoff) {
                File::delete($file);
                $removed++;
            }
        }

        if ($removed > 0) {
            $this->info("Menghapus {$removed} cadangan lama (lebih dari {$keepDays} hari).");
        }
    }

    private function humanSize(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
