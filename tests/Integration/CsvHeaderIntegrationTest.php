<?php

namespace App\Tests\Integration;

use App\Service\CsvService;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for CSV file headers
 * Based on test_process.php - tests actual CSV files
 */
final class CsvHeaderIntegrationTest extends TestCase
{
    private CsvService $csvService;
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->csvService = new CsvService();
        $this->projectRoot = dirname(__DIR__, 2);
    }

    /**
     * Test cadastral file header contains expected columns
     * Based on test_process.php line 49-83
     */
    public function testCadastralFileHeaderContainsExpectedColumns(): void
    {
        $file = $this->projectRoot . '/parcelles_cadastrales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Cadastral file not found: $file");
        }

        $header = $this->csvService->getHeader($file);

        // Expected columns from test_process.php
        $expectedColumns = [
            'NUM_DEPT',
            'NUM_COM',
            'N_SECTION',
            'N_PARCELLE',
            'id_parcellaire',
            'Geo Shape',
        ];

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains(
                $expectedColumn,
                $header,
                "Cadastral file should contain column '$expectedColumn'"
            );
        }
    }

    /**
     * Test cadastral file header columns can be found by index
     */
    public function testCadastralFileColumnIndices(): void
    {
        $file = $this->projectRoot . '/parcelles_cadastrales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Cadastral file not found: $file");
        }

        $header = $this->csvService->getHeader($file);

        $expectedColumns = [
            'NUM_DEPT',
            'NUM_COM',
            'N_SECTION',
            'N_PARCELLE',
            'id_parcellaire',
            'Geo Shape',
        ];

        foreach ($expectedColumns as $col) {
            $index = array_search($col, $header);
            $this->assertNotFalse(
                $index,
                "Column '$col' should be found in header (found at index: " .
                ($index !== false ? $index : 'NOT FOUND') . ")"
            );
        }
    }

    /**
     * Test social housing file header contains expected columns
     * Based on test_process.php line 85-115
     */
    public function testSocialFileHeaderContainsExpectedColumns(): void
    {
        $file = $this->projectRoot . '/parcelles-des-personnes-morales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Social housing file not found: $file");
        }

        $header = $this->csvService->getHeader($file);

        // Expected columns from test_process.php
        $expectedColumns = [
            '_parcelle_coords.coord',
            'code_parcelle',
        ];

        foreach ($expectedColumns as $expectedColumn) {
            $this->assertContains(
                $expectedColumn,
                $header,
                "Social housing file should contain column '$expectedColumn'"
            );
        }
    }

    /**
     * Test social housing file column indices
     */
    public function testSocialFileColumnIndices(): void
    {
        $file = $this->projectRoot . '/parcelles-des-personnes-morales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Social housing file not found: $file");
        }

        $header = $this->csvService->getHeader($file);

        $expectedColumns = [
            '_parcelle_coords.coord',
            'code_parcelle',
        ];

        foreach ($expectedColumns as $col) {
            $index = array_search($col, $header);
            $this->assertNotFalse(
                $index,
                "Column '$col' should be found in header (found at index: " .
                ($index !== false ? $index : 'NOT FOUND') . ")"
            );
        }
    }

    /**
     * Test that header cleaning removes BOM from actual files
     */
    public function testHeaderCleaningRemovesBomFromRealFiles(): void
    {
        $file = $this->projectRoot . '/parcelles_cadastrales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Cadastral file not found: $file");
        }

        $header = $this->csvService->getHeader($file);

        // First column should not contain BOM characters
        if (!empty($header[0])) {
            $this->assertStringNotContainsString(
                "\xEF\xBB\xBF",
                $header[0],
                'First column should not contain UTF-8 BOM'
            );
        }
    }

    /**
     * Test counting lines in cadastral file
     */
    public function testCountLinesInCadastralFile(): void
    {
        $file = $this->projectRoot . '/parcelles_cadastrales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Cadastral file not found: $file");
        }

        $count = $this->csvService->countLines($file);

        $this->assertGreaterThan(0, $count, 'Cadastral file should have at least one data row');
    }

    /**
     * Test counting lines in social housing file
     */
    public function testCountLinesInSocialFile(): void
    {
        $file = $this->projectRoot . '/parcelles-des-personnes-morales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Social housing file not found: $file");
        }

        $count = $this->csvService->countLines($file);

        $this->assertGreaterThan(0, $count, 'Social housing file should have at least one data row');
    }

    /**
     * Test reading first row of cadastral file
     */
    public function testReadFirstRowOfCadastralFile(): void
    {
        $file = $this->projectRoot . '/parcelles_cadastrales.csv';

        if (!file_exists($file)) {
            $this->markTestSkipped("Cadastral file not found: $file");
        }

        $generator = $this->csvService->readCsv($file);
        $firstItem = null;

        foreach ($generator as $item) {
            $firstItem = $item;
            break;
        }

        $this->assertNotNull($firstItem, 'Should be able to read at least one row');
        $this->assertArrayHasKey('header', $firstItem);
        $this->assertArrayHasKey('row', $firstItem);
        $this->assertIsArray($firstItem['header']);
        $this->assertIsArray($firstItem['row']);
        $this->assertNotEmpty($firstItem['header'], 'Header should not be empty');
    }
}
