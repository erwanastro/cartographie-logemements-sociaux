<?php

namespace App\Service;

/**
 * Service for CSV file operations
 * Single Responsibility: Handle all CSV-related operations
 */
final readonly class CsvService
{
    /**
     * Clean CSV header by removing BOM, quotes, and spaces
     */
    public function cleanHeader(array $header): array
    {
        // Remove UTF-8 BOM from first element if present
        if (!empty($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
        }

        // Remove quotes and spaces
        return array_map(function($col) {
            return trim($col, ' "');
        }, $header);
    }

    /**
     * Count lines in a CSV file (excluding header)
     */
    public function countLines(string $filename): int
    {
        if (!file_exists($filename)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($filename, 'r');

        if (!$handle) {
            return 0;
        }

        // Skip header
        fgetcsv($handle, 0, ';', '"', '\\');

        while (fgetcsv($handle, 0, ';', '"', '\\') !== false) {
            $count++;
        }

        fclose($handle);
        return $count;
    }

    /**
     * Read CSV file and yield rows with cleaned headers
     *
     * @return \Generator Returns generator with ['header' => array, 'row' => array]
     */
    public function readCsv(string $filename): \Generator
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("File not found: $filename");
        }

        $handle = fopen($filename, 'r');

        if (!$handle) {
            throw new \RuntimeException("Cannot open file: $filename");
        }

        // Read and clean header
        $header = fgetcsv($handle, 0, ';', '"', '\\');
        $header = $this->cleanHeader($header);

        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            yield ['header' => $header, 'row' => $row];
        }

        fclose($handle);
    }

    /**
     * Get CSV header
     */
    public function getHeader(string $filename): array
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("File not found: $filename");
        }

        $handle = fopen($filename, 'r');

        if (!$handle) {
            throw new \RuntimeException("Cannot open file: $filename");
        }

        $header = fgetcsv($handle, 0, ';', '"', '\\');
        fclose($handle);

        return $this->cleanHeader($header);
    }

    /**
     * Write CSV file
     */
    public function writeCsv(string $filename, array $rows): void
    {
        $handle = fopen($filename, 'w');

        if (!$handle) {
            throw new \RuntimeException("Cannot create file: $filename");
        }

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }

        fclose($handle);
    }

    /**
     * Create a backup of a file
     */
    public function createBackup(string $filename): string
    {
        if (!file_exists($filename)) {
            throw new \RuntimeException("File not found: $filename");
        }

        $backupFile = $filename . '.backup.' . date('Ymd_His');

        if (!copy($filename, $backupFile)) {
            throw new \RuntimeException("Cannot create backup file");
        }

        return $backupFile;
    }
}
