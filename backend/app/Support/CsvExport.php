<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de CSV pensada para Excel en español: separador ";", UTF-8 con BOM
 * y celdas de texto neutralizadas contra inyección de fórmulas.
 */
class CsvExport
{
    /**
     * @param  string[]  $headers
     * @param  iterable<array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel reconoce los acentos.

            fputcsv($out, $headers, ';', '"', '\\');

            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row), ';', '"', '\\');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Un texto que empieza con = + - @ (o tab/enter) Excel lo ejecutaría como fórmula:
     * se le antepone una comilla simple. Los números y fechas no se tocan.
     */
    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
