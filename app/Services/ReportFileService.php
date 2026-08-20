<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportFileService
{
    /** @param array<string, mixed> $data */
    public function pdf(string $view, array $data, string $filename, string $orientation = 'portrait'): Response
    {
        return Pdf::loadView($view, $data + ['isPdf' => true])
            ->setPaper('a4', $orientation)
            ->download($this->safeFilename($filename));
    }

    /** @param array<int, string> $headings @param iterable<int, array<int, mixed>> $rows */
    public function csv(array $headings, iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headings);
            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($value) => is_array($value) ? implode(', ', $value) : $value, $row));
            }
            fclose($output);
        }, $this->safeFilename($filename), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function safeFilename(string $filename): string
    {
        return trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename), '_');
    }
}
