<?php

namespace App\Command;

use App\Service\CadastralDataService;
use App\Service\CsvService;
use App\Service\GeoJsonService;
use App\Service\MajicConverterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:process-parcelles',
    description: 'Process social housing parcels with cadastral data'
)]
final class ProcessParcellesCommand extends Command
{
    public function __construct(
        private readonly CsvService $csvService,
        private readonly MajicConverterService $majicConverter,
        private readonly CadastralDataService $cadastralService,
        private readonly GeoJsonService $geoJsonService,
        #[Autowire(param: 'input_file_social')]
        private readonly string $inputFileSocial,
        #[Autowire(param: 'input_file_cadastral')]
        private readonly string $inputFileCadastral,
        #[Autowire(param: 'output_file_csv')]
        private readonly string $outputFileCsv,
        #[Autowire(param: 'output_file_geojson')]
        private readonly string $outputFileGeoJson,
        #[Autowire(param: 'col_gps_coords')]
        private readonly string $colGpsCoords,
        #[Autowire(param: 'col_code_parcelle')]
        private readonly string $colCodeParcelle,
        #[Autowire(param: 'col_id_parcellaire')]
        private readonly string $colIdParcellaire,
        #[Autowire(param: 'col_address')]
        private readonly string $colAddress,
        #[Autowire(param: 'col_group_person')]
        private readonly string $colGroupPerson,
        #[Autowire(param: 'col_cadastral_id')]
        private readonly string $colCadastralId,
        #[Autowire(param: 'col_cadastral_geo')]
        private readonly string $colCadastralGeo,
        #[Autowire(param: 'col_cadastral_lat')]
        private readonly string $colCadastralLat,
        #[Autowire(param: 'col_cadastral_lon')]
        private readonly string $colCadastralLon,
        #[Autowire(param: 'location_name')]
        private readonly string $locationName
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startTime = microtime(true);

        $io->title('Process Social Housing Parcels');

        // Load cadastral data
        $io->section('Loading cadastral data...');
        try {
            $cadastralData = $this->cadastralService->loadCadastralData(
                $this->inputFileCadastral,
                $this->colCadastralId,
                $this->colCadastralGeo,
                $this->colCadastralLat,
                $this->colCadastralLon
            );
            $io->success("Cadastral data loaded: " . count($cadastralData) . " entries");
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // Count lines
        $io->section('Counting lines to process...');
        $totalLines = $this->csvService->countLines($this->inputFileSocial);
        $io->info("Total: $totalLines social housing parcels");

        // Check if file exists
        if (!file_exists($this->inputFileSocial)) {
            $io->error("File not found: {$this->inputFileSocial}");
            return Command::FAILURE;
        }

        // Read header
        $header = $this->csvService->getHeader($this->inputFileSocial);

        $colIndexCoords = array_search($this->colGpsCoords, $header);
        $colIndexCodeParcelle = array_search($this->colCodeParcelle, $header);
        $colIndexIdParcellaire = array_search($this->colIdParcellaire, $header);
        $colIndexAddress = array_search($this->colAddress, $header);
        $colIndexGroup = array_search($this->colGroupPerson, $header);

        if ($colIndexCoords === false || $colIndexCodeParcelle === false) {
            $io->error('Required columns not found');
            return Command::FAILURE;
        }

        // Check if id_parcellaire column is available
        $hasIdParcellaire = ($colIndexIdParcellaire !== false);
        if ($hasIdParcellaire) {
            $io->info('✓ Column id_parcellaire detected - using direct matching');
        } else {
            $io->warning('⚠ Column id_parcellaire not found - will convert MAJIC → CNIG');
        }

        // Prepare output file
        $outHandle = fopen($this->outputFileCsv, 'w');

        if (!$outHandle) {
            $io->error("Cannot create output file: {$this->outputFileCsv}");
            return Command::FAILURE;
        }

        // Write output header
        $outputHeader = $header;
        $outputHeader[] = 'name';
        $outputHeader[] = $this->colCadastralGeo;
        fputcsv($outHandle, $outputHeader, ';', '"', '\\');

        // Process rows
        $io->section('Processing...');
        $progressBar = new ProgressBar($output, $totalLines);
        $progressBar->start();

        $matchedCount = 0;
        $unmatchedCount = 0;
        $pointGeometryCount = 0;
        $geoJsonFeatures = [];

        foreach ($this->csvService->readCsv($this->inputFileSocial) as $item) {
            $row = $item['row'];

            $majicCode = trim($row[$colIndexCodeParcelle], '"');
            $gpsCoords = trim($row[$colIndexCoords], '"');

            // Get parcel ID and convert to CNIG format if needed
            if ($hasIdParcellaire) {
                $parcelId = trim($row[$colIndexIdParcellaire], '"');
                // Auto-detect format and convert if MAJIC (14 chars)
                if (strlen($parcelId) === 14) {
                    $cnigCode = $this->majicConverter->convertToCnigPci($parcelId);
                } else {
                    // Already in CNIG format (12 chars) or other format
                    $cnigCode = $parcelId;
                }
            } else {
                // Fallback to code_parcelle column (assumed MAJIC format)
                $cnigCode = $this->majicConverter->convertToCnigPci($majicCode);
            }

            $geometry = '';
            $geometryArray = null;
            $matched = false;

            // Try to find polygon by CNIG code
            if (isset($cadastralData[$cnigCode])) {
                $geometry = $cadastralData[$cnigCode]['geo'];
                $geometryArray = json_decode($geometry, true);
                $matchedCount++;
                $matched = true;
            }

            // If no polygon found, create Point geometry from GPS
            if (!$matched && !empty($gpsCoords)) {
                $geometry = $this->geoJsonService->createPointGeometry($gpsCoords);
                $geometryArray = json_decode($geometry, true);
                $pointGeometryCount++;
            } else if ($matched) {
                // Polygon found - clear GPS column
                $row[$colIndexCoords] = '';
            }

            if (!$matched) {
                $unmatchedCount++;
            }

            // Add name and geometry to CSV
            $row[] = $this->locationName;
            $row[] = $geometry;

            // Write CSV row
            fputcsv($outHandle, $row, ';', '"', '\\');

            // Add GeoJSON feature
            if ($geometryArray !== null) {
                $feature = $this->geoJsonService->createFeature($geometryArray, [
                    'name' => $this->locationName,
                    'code_parcelle' => $majicCode,
                    'adresse' => isset($row[$colIndexAddress]) ? trim($row[$colIndexAddress], ' "') : '',
                    'groupe_personne' => isset($row[$colIndexGroup]) ? trim($row[$colIndexGroup], ' "') : ''
                ]);
                $geoJsonFeatures[] = $feature;
            }

            $progressBar->advance();
        }

        fclose($outHandle);
        $progressBar->finish();
        $io->newLine(2);

        // Write GeoJSON file
        $io->section('Creating GeoJSON file...');
        $geoJson = $this->geoJsonService->createFeatureCollection($geoJsonFeatures);

        try {
            $this->geoJsonService->writeGeoJson($this->outputFileGeoJson, $geoJson);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        // Display summary
        $io->section('Processing complete');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total lines processed', $totalLines],
                ['Geometries exported', $matchedCount + $pointGeometryCount],
                ['- Cadastral polygons', $matchedCount],
                ['- GPS points', $pointGeometryCount],
                ['Lines without geometry', $unmatchedCount - $pointGeometryCount],
                ['Processing duration', "{$duration}s"],
            ]
        );

        $io->success('Output files:');
        $io->listing([
            "CSV: {$this->outputFileCsv}",
            "GeoJSON: {$this->outputFileGeoJson}"
        ]);

        return Command::SUCCESS;
    }
}
