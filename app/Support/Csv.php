<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pembuat berkas CSV untuk seluruh ekspor laporan.
 */
class Csv
{
    /**
     * PHP 8.4 mendeprekasi nilai bawaan $escape pada fputcsv(). String kosong
     * mematikan escaping backslash non-standar, yaitu perilaku sesuai RFC 4180
     * dan yang akan menjadi bawaan pada versi PHP berikutnya.
     */
    private const ESCAPE = '';

    /**
     * Kirim CSV sebagai unduhan streaming.
     *
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 supaya Excel tidak merusak huruf beraksen / nama Indonesia
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $header, escape: self::ESCAPE);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(static::stringify(...), $row), escape: self::ESCAPE);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Nama berkas berstempel tanggal, mis. "nilai-laravel-dasar-2026-07-29.csv".
     */
    public static function filename(string $prefix): string
    {
        return $prefix.'-'.now()->format('Y-m-d').'.csv';
    }

    /**
     * Susun isi CSV sebagai teks (dipakai pengujian).
     *
     * @param  array<int, string>  $header
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public static function toString(array $header, iterable $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header, escape: self::ESCAPE);

        foreach ($rows as $row) {
            fputcsv($handle, array_map(static::stringify(...), $row), escape: self::ESCAPE);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return $contents;
    }

    /**
     * Nilai null ditulis sebagai sel kosong, bukan tulisan "null".
     */
    private static function stringify(mixed $value): string
    {
        return $value === null ? '' : (string) $value;
    }
}
