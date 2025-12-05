<?php

namespace App\Tests\Unit\Service;

use App\Service\CsvService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CsvService
 */
final class CsvServiceTest extends TestCase
{
    private CsvService $service;
    private string $testDir;

    protected function setUp(): void
    {
        $this->service = new CsvService();
        $this->testDir = sys_get_temp_dir() . '/csv_test_' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        // Clean up test files
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }

    /**
     * Test cleaning CSV header with UTF-8 BOM
     */
    public function testCleanHeaderRemovesUtf8Bom(): void
    {
        $header = ["\xEF\xBB\xBF" . 'Column1', 'Column2', 'Column3'];
        $cleaned = $this->service->cleanHeader($header);

        $this->assertSame('Column1', $cleaned[0], 'UTF-8 BOM should be removed from first column');
        $this->assertSame('Column2', $cleaned[1]);
        $this->assertSame('Column3', $cleaned[2]);
    }

    /**
     * Test cleaning CSV header with quotes
     */
    public function testCleanHeaderRemovesQuotes(): void
    {
        $header = ['"Column1"', ' "Column2" ', '"Column3"'];
        $cleaned = $this->service->cleanHeader($header);

        $this->assertSame('Column1', $cleaned[0], 'Quotes should be removed');
        $this->assertSame('Column2', $cleaned[1], 'Quotes and spaces should be removed');
        $this->assertSame('Column3', $cleaned[2], 'Quotes should be removed');
    }

    /**
     * Test cleaning CSV header with spaces
     */
    public function testCleanHeaderRemovesSpaces(): void
    {
        $header = [' Column1 ', '  Column2  ', 'Column3'];
        $cleaned = $this->service->cleanHeader($header);

        $this->assertSame('Column1', $cleaned[0], 'Leading/trailing spaces should be removed');
        $this->assertSame('Column2', $cleaned[1], 'Leading/trailing spaces should be removed');
        $this->assertSame('Column3', $cleaned[2]);
    }

    /**
     * Test cleaning CSV header with BOM, quotes and spaces combined
     */
    public function testCleanHeaderCombinedCleaning(): void
    {
        $header = ["\xEF\xBB\xBF" . ' "Column1" ', ' "Column2" ', '"Column3"'];
        $cleaned = $this->service->cleanHeader($header);

        $this->assertSame('Column1', $cleaned[0], 'BOM, quotes and spaces should be removed');
        $this->assertSame('Column2', $cleaned[1], 'Quotes and spaces should be removed');
        $this->assertSame('Column3', $cleaned[2], 'Quotes should be removed');
    }

    /**
     * Test cleaning empty header
     */
    public function testCleanHeaderWithEmptyArray(): void
    {
        $header = [];
        $cleaned = $this->service->cleanHeader($header);

        $this->assertSame([], $cleaned, 'Empty array should remain empty');
    }

    /**
     * Test count lines in CSV file
     */
    public function testCountLines(): void
    {
        $file = $this->testDir . '/test.csv';
        $data = [
            ['Header1', 'Header2', 'Header3'],
            ['Row1Col1', 'Row1Col2', 'Row1Col3'],
            ['Row2Col1', 'Row2Col2', 'Row2Col3'],
            ['Row3Col1', 'Row3Col2', 'Row3Col3'],
        ];

        $handle = fopen($file, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }
        fclose($handle);

        $count = $this->service->countLines($file);

        $this->assertSame(3, $count, 'Should count 3 data rows (excluding header)');
    }

    /**
     * Test count lines with non-existent file
     */
    public function testCountLinesWithNonExistentFile(): void
    {
        $count = $this->service->countLines('/non/existent/file.csv');

        $this->assertSame(0, $count, 'Non-existent file should return 0');
    }

    /**
     * Test get header from CSV file
     */
    public function testGetHeader(): void
    {
        $file = $this->testDir . '/test.csv';
        $expectedHeader = ['Column1', 'Column2', 'Column3'];

        $handle = fopen($file, 'w');
        fputcsv($handle, $expectedHeader, ';', '"', '\\');
        fputcsv($handle, ['Data1', 'Data2', 'Data3'], ';', '"', '\\');
        fclose($handle);

        $header = $this->service->getHeader($file);

        $this->assertSame($expectedHeader, $header);
    }

    /**
     * Test get header with UTF-8 BOM and quotes
     */
    public function testGetHeaderWithBomAndQuotes(): void
    {
        $file = $this->testDir . '/test_bom.csv';

        // Write file with BOM and quotes
        $handle = fopen($file, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($handle, ['"Column1"', '"Column2"', '"Column3"'], ';', '"', '\\');
        fclose($handle);

        $header = $this->service->getHeader($file);

        $this->assertSame('Column1', $header[0], 'BOM and quotes should be removed');
        $this->assertSame('Column2', $header[1], 'Quotes should be removed');
        $this->assertSame('Column3', $header[2], 'Quotes should be removed');
    }

    /**
     * Test write CSV file
     */
    public function testWriteCsv(): void
    {
        $file = $this->testDir . '/output.csv';
        $rows = [
            ['Header1', 'Header2', 'Header3'],
            ['Data1', 'Data2', 'Data3'],
            ['Data4', 'Data5', 'Data6'],
        ];

        $this->service->writeCsv($file, $rows);

        $this->assertFileExists($file);

        // Verify content
        $handle = fopen($file, 'r');
        $readRows = [];
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            $readRows[] = $row;
        }
        fclose($handle);

        $this->assertSame($rows, $readRows);
    }

    /**
     * Test create backup
     */
    public function testCreateBackup(): void
    {
        $file = $this->testDir . '/original.csv';
        file_put_contents($file, "test content");

        $backupFile = $this->service->createBackup($file);

        $this->assertFileExists($backupFile);
        $this->assertStringContainsString('.backup.', $backupFile);
        $this->assertSame('test content', file_get_contents($backupFile));
    }

    /**
     * Test create backup with non-existent file
     */
    public function testCreateBackupWithNonExistentFile(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $this->service->createBackup('/non/existent/file.csv');
    }

    /**
     * Test read CSV returns generator
     */
    public function testReadCsvReturnsGenerator(): void
    {
        $file = $this->testDir . '/test.csv';
        $data = [
            ['Header1', 'Header2', 'Header3'],
            ['Row1Col1', 'Row1Col2', 'Row1Col3'],
            ['Row2Col1', 'Row2Col2', 'Row2Col3'],
        ];

        $handle = fopen($file, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }
        fclose($handle);

        $generator = $this->service->readCsv($file);

        $this->assertInstanceOf(\Generator::class, $generator);

        $count = 0;
        foreach ($generator as $item) {
            $this->assertArrayHasKey('header', $item);
            $this->assertArrayHasKey('row', $item);
            $this->assertSame(['Header1', 'Header2', 'Header3'], $item['header']);
            $count++;
        }

        $this->assertSame(2, $count, 'Should yield 2 data rows');
    }

    /**
     * Test read CSV with non-existent file throws exception
     */
    public function testReadCsvWithNonExistentFileThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        $generator = $this->service->readCsv('/non/existent/file.csv');
        iterator_to_array($generator); // Force generator execution
    }
}
