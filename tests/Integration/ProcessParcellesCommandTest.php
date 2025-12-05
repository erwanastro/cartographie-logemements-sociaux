<?php

namespace App\Tests\Integration;

use App\Command\ProcessParcellesCommand;
use App\Service\CadastralDataService;
use App\Service\CsvService;
use App\Service\GeoJsonService;
use App\Service\MajicConverterService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration test for ProcessParcellesCommand
 * Tests with fake data to verify CSV output consistency
 */
final class ProcessParcellesCommandTest extends TestCase
{
    private string $testDir;
    private string $fakeSocialFile;
    private string $fakeCadastralFile;
    private string $outputCsvFile;
    private string $outputGeoJsonFile;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/process_test_' . uniqid();
        mkdir($this->testDir);

        $this->fakeSocialFile = $this->testDir . '/social.csv';
        $this->fakeCadastralFile = $this->testDir . '/cadastral.csv';
        $this->outputCsvFile = $this->testDir . '/output.csv';
        $this->outputGeoJsonFile = $this->testDir . '/output.geojson';

        $this->createFakeSocialFile();
        $this->createFakeCadastralFile();
    }

    protected function tearDown(): void
    {
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
     * Create fake social housing CSV file with test data
     */
    private function createFakeSocialFile(): void
    {
        $data = [
            // Header
            ['_parcelle_coords.coord', 'code_parcelle', 'id_parcellaire', 'adresse', 'groupe_personne'],
            // Data rows with MAJIC codes
            ['48.5839,7.7455', '67482000010017', '674820010017', '1 Rue Test', 'Société A'],
            ['48.5840,7.7456', '67482000010018', '674820010018', '2 Rue Test', 'Société B'],
            ['48.5841,7.7457', '67482000040014', '674820040014', '3 Rue Test', 'Société C'],
            // Row without GPS but with valid CNIG code
            ['', '67482000050001', '674820050001', '4 Rue Test', 'Société D'],
            // Row with GPS but invalid parcel code
            ['48.5842,7.7458', 'INVALID', '', '5 Rue Test', 'Société E'],
        ];

        $handle = fopen($this->fakeSocialFile, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }
        fclose($handle);
    }

    /**
     * Create fake cadastral CSV file with test data
     */
    private function createFakeCadastralFile(): void
    {
        $data = [
            // Header
            ['NUM_DEPT', 'NUM_COM', 'N_SECTION', 'N_PARCELLE', 'id_parcellaire', 'Geo Shape', 'N_PARC_Y', 'N_PARC_X'],
            // Data rows with CNIG codes matching social file
            [
                '67', '482', '001', '0017', '674820010017',
                '{"type":"Polygon","coordinates":[[[7.7455,48.5839],[7.7456,48.5839],[7.7456,48.5840],[7.7455,48.5840],[7.7455,48.5839]]]}',
                '48.5839', '7.7455'
            ],
            [
                '67', '482', '001', '0018', '674820010018',
                '{"type":"Polygon","coordinates":[[[7.7456,48.5840],[7.7457,48.5840],[7.7457,48.5841],[7.7456,48.5841],[7.7456,48.5840]]]}',
                '48.5840', '7.7456'
            ],
            [
                '67', '482', '004', '0014', '674820040014',
                '{"type":"Polygon","coordinates":[[[7.7457,48.5841],[7.7458,48.5841],[7.7458,48.5842],[7.7457,48.5842],[7.7457,48.5841]]]}',
                '48.5841', '7.7457'
            ],
            [
                '67', '482', '005', '0001', '674820050001',
                '{"type":"Polygon","coordinates":[[[7.7458,48.5842],[7.7459,48.5842],[7.7459,48.5843],[7.7458,48.5843],[7.7458,48.5842]]]}',
                '48.5842', '7.7458'
            ],
        ];

        $handle = fopen($this->fakeCadastralFile, 'w');
        foreach ($data as $row) {
            fputcsv($handle, $row, ';', '"', '\\');
        }
        fclose($handle);
    }

    /**
     * Test that ProcessParcellesCommand produces coherent output
     */
    public function testProcessParcellesCommandOutputIsCoherent(): void
    {
        // Create command
        $csvService = new CsvService();
        $majicConverter = new MajicConverterService();
        $geoJsonService = new GeoJsonService();
        $cadastralService = new CadastralDataService($csvService);

        $command = new ProcessParcellesCommand(
            csvService: $csvService,
            majicConverter: $majicConverter,
            cadastralService: $cadastralService,
            geoJsonService: $geoJsonService,
            inputFileSocial: $this->fakeSocialFile,
            inputFileCadastral: $this->fakeCadastralFile,
            outputFileCsv: $this->outputCsvFile,
            outputFileGeoJson: $this->outputGeoJsonFile,
            colGpsCoords: '_parcelle_coords.coord',
            colCodeParcelle: 'code_parcelle',
            colIdParcellaire: 'id_parcellaire',
            colAddress: 'adresse',
            colGroupPerson: 'groupe_personne',
            colCadastralId: 'id_parcellaire',
            colCadastralGeo: 'Geo Shape',
            colCadastralLat: 'N_PARC_Y',
            colCadastralLon: 'N_PARC_X',
            locationName: 'Test Location'
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Verify command succeeded
        $this->assertSame(0, $commandTester->getStatusCode(), 'Command should succeed');

        // Verify output files exist
        $this->assertFileExists($this->outputCsvFile, 'CSV output file should exist');
        $this->assertFileExists($this->outputGeoJsonFile, 'GeoJSON output file should exist');

        // Read output CSV
        $outputData = [];
        foreach ($csvService->readCsv($this->outputCsvFile) as $item) {
            $outputData[] = [
                'header' => $item['header'],
                'row' => $item['row']
            ];
        }

        // Verify we have output data
        $this->assertNotEmpty($outputData, 'Output CSV should contain data');

        // Read input social file to compare
        $inputData = [];
        foreach ($csvService->readCsv($this->fakeSocialFile) as $item) {
            $inputData[] = [
                'header' => $item['header'],
                'row' => $item['row']
            ];
        }

        // Verify same number of rows
        $this->assertCount(
            count($inputData),
            $outputData,
            'Output should have same number of rows as input'
        );

        // Get column indices
        $header = $outputData[0]['header'];
        $colIndexCode = array_search('code_parcelle', $header);
        $colIndexAddress = array_search('adresse', $header);
        $colIndexGroup = array_search('groupe_personne', $header);
        $colIndexGeo = array_search('Geo Shape', $header);
        $colIndexName = array_search('name', $header);

        $this->assertNotFalse($colIndexCode, 'Output should have code_parcelle column');
        $this->assertNotFalse($colIndexGeo, 'Output should have Geo Shape column');
        $this->assertNotFalse($colIndexName, 'Output should have name column');

        // Verify data consistency
        foreach ($outputData as $index => $output) {
            $row = $output['row'];
            $inputRow = $inputData[$index]['row'];

            // Verify original data is preserved
            $this->assertSame(
                $inputRow[$colIndexCode],
                $row[$colIndexCode],
                "Row $index: code_parcelle should be preserved"
            );

            if ($colIndexAddress !== false) {
                $this->assertSame(
                    $inputRow[$colIndexAddress],
                    $row[$colIndexAddress],
                    "Row $index: address should be preserved"
                );
            }

            if ($colIndexGroup !== false) {
                $this->assertSame(
                    $inputRow[$colIndexGroup],
                    $row[$colIndexGroup],
                    "Row $index: group should be preserved"
                );
            }

            // Verify geometry was added (except for invalid rows)
            if ($row[$colIndexCode] !== 'INVALID') {
                $this->assertNotEmpty(
                    $row[$colIndexGeo],
                    "Row $index: Geo Shape should not be empty for valid code"
                );
            }

            // Verify location name was added
            $this->assertSame(
                'Test Location',
                $row[$colIndexName],
                "Row $index: name should be 'Test Location'"
            );
        }
    }

    /**
     * Test that matched parcels have polygon geometries
     */
    public function testMatchedParcelsHavePolygonGeometries(): void
    {
        // Run command
        $csvService = new CsvService();
        $majicConverter = new MajicConverterService();
        $geoJsonService = new GeoJsonService();
        $cadastralService = new CadastralDataService($csvService);

        $command = new ProcessParcellesCommand(
            csvService: $csvService,
            majicConverter: $majicConverter,
            cadastralService: $cadastralService,
            geoJsonService: $geoJsonService,
            inputFileSocial: $this->fakeSocialFile,
            inputFileCadastral: $this->fakeCadastralFile,
            outputFileCsv: $this->outputCsvFile,
            outputFileGeoJson: $this->outputGeoJsonFile,
            colGpsCoords: '_parcelle_coords.coord',
            colCodeParcelle: 'code_parcelle',
            colIdParcellaire: 'id_parcellaire',
            colAddress: 'adresse',
            colGroupPerson: 'groupe_personne',
            colCadastralId: 'id_parcellaire',
            colCadastralGeo: 'Geo Shape',
            colCadastralLat: 'N_PARC_Y',
            colCadastralLon: 'N_PARC_X',
            locationName: 'Test Location'
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Read output CSV
        $outputData = [];
        foreach ($csvService->readCsv($this->outputCsvFile) as $item) {
            $outputData[] = $item;
        }

        $header = $outputData[0]['header'];
        $colIndexGeo = array_search('Geo Shape', $header);
        $colIndexCode = array_search('code_parcelle', $header);

        // Check matched parcels
        $polygonCount = 0;

        foreach ($outputData as $item) {
            $row = $item['row'];
            $geoShape = $row[$colIndexGeo];

            if (empty($geoShape)) {
                continue;
            }

            $geometry = json_decode($geoShape, true);
            $this->assertIsArray($geometry, 'Geometry should be valid JSON');
            $this->assertArrayHasKey('type', $geometry, 'Geometry should have type');

            if ($geometry['type'] === 'Polygon') {
                $polygonCount++;
            }
        }

        // We should have 4 polygons (matched parcels) from our fake data
        $this->assertGreaterThanOrEqual(
            4,
            $polygonCount,
            'Should have at least 4 polygon geometries from matched parcels'
        );
    }

    /**
     * Test GeoJSON output is valid
     */
    public function testGeoJsonOutputIsValid(): void
    {
        // Run command
        $csvService = new CsvService();
        $majicConverter = new MajicConverterService();
        $geoJsonService = new GeoJsonService();
        $cadastralService = new CadastralDataService($csvService);

        $command = new ProcessParcellesCommand(
            csvService: $csvService,
            majicConverter: $majicConverter,
            cadastralService: $cadastralService,
            geoJsonService: $geoJsonService,
            inputFileSocial: $this->fakeSocialFile,
            inputFileCadastral: $this->fakeCadastralFile,
            outputFileCsv: $this->outputCsvFile,
            outputFileGeoJson: $this->outputGeoJsonFile,
            colGpsCoords: '_parcelle_coords.coord',
            colCodeParcelle: 'code_parcelle',
            colIdParcellaire: 'id_parcellaire',
            colAddress: 'adresse',
            colGroupPerson: 'groupe_personne',
            colCadastralId: 'id_parcellaire',
            colCadastralGeo: 'Geo Shape',
            colCadastralLat: 'N_PARC_Y',
            colCadastralLon: 'N_PARC_X',
            locationName: 'Test Location'
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        // Read and parse GeoJSON
        $geoJsonContent = file_get_contents($this->outputGeoJsonFile);
        $geoJson = json_decode($geoJsonContent, true);

        $this->assertIsArray($geoJson, 'GeoJSON should be valid JSON');
        $this->assertArrayHasKey('type', $geoJson, 'GeoJSON should have type');
        $this->assertSame('FeatureCollection', $geoJson['type'], 'Type should be FeatureCollection');
        $this->assertArrayHasKey('features', $geoJson, 'GeoJSON should have features');
        $this->assertIsArray($geoJson['features'], 'Features should be an array');
        $this->assertNotEmpty($geoJson['features'], 'Should have at least one feature');

        // Verify each feature structure
        foreach ($geoJson['features'] as $feature) {
            $this->assertArrayHasKey('type', $feature);
            $this->assertSame('Feature', $feature['type']);
            $this->assertArrayHasKey('geometry', $feature);
            $this->assertArrayHasKey('properties', $feature);
            $this->assertArrayHasKey('name', $feature['properties']);
            $this->assertSame('Test Location', $feature['properties']['name']);
        }
    }
}
