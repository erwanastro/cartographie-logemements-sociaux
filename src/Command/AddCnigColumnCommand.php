<?php

namespace App\Command;

use App\Service\CsvService;
use App\Service\MajicConverterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:add-cnig-column',
    description: 'Add id_parcellaire column in CNIG PCI format to social housing file'
)]
final class AddCnigColumnCommand extends Command
{
    public function __construct(
        private readonly CsvService $csvService,
        private readonly MajicConverterService $majicConverter,
        #[Autowire(param: 'input_file_social')]
        private readonly string $inputFileSocial,
        #[Autowire(param: 'col_code_parcelle')]
        private readonly string $colCodeParcelle,
        #[Autowire(param: 'col_id_parcellaire')]
        private readonly string $colIdParcellaire
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Add id_parcellaire column (CNIG PCI format)');

        // Check if file exists
        if (!file_exists($this->inputFileSocial)) {
            $io->error("File not found: {$this->inputFileSocial}");
            return Command::FAILURE;
        }

        $io->info("Input file: {$this->inputFileSocial}");

        // Create backup
        $io->section('Creating backup...');
        try {
            $backupFile = $this->csvService->createBackup($this->inputFileSocial);
            $io->success("Backup created: $backupFile");
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // Read header
        $header = $this->csvService->getHeader($this->inputFileSocial);

        // Check if columns exist
        $colIndexCodeParcelle = array_search($this->colCodeParcelle, $header);
        $colIndexIdParcellaire = array_search($this->colIdParcellaire, $header);

        if ($colIndexCodeParcelle === false) {
            $io->error("Column '{$this->colCodeParcelle}' not found");
            return Command::FAILURE;
        }

        // Check if id_parcellaire column already exists
        if ($colIndexIdParcellaire !== false) {
            $io->warning("Column '{$this->colIdParcellaire}' already exists at index $colIndexIdParcellaire");

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to update it? (y/n) ', false);

            if (!$helper->ask($input, $output, $question)) {
                $io->info('Operation cancelled');
                return Command::SUCCESS;
            }
        }

        // Count lines
        $io->section('Counting lines...');
        $totalLines = $this->csvService->countLines($this->inputFileSocial);
        $io->info("Total: $totalLines lines");

        // Create temporary file
        $tempFile = $this->inputFileSocial . '.tmp';
        $outHandle = fopen($tempFile, 'w');

        if (!$outHandle) {
            $io->error('Cannot create temporary file');
            return Command::FAILURE;
        }

        // Prepare output header
        if ($colIndexIdParcellaire === false) {
            $outputHeader = $header;
            $outputHeader[] = $this->colIdParcellaire;
            $colIndexIdParcellaire = count($outputHeader) - 1;
        } else {
            $outputHeader = $header;
        }

        // Write header
        fputcsv($outHandle, $outputHeader, ';', '"', '\\');

        // Process rows
        $io->section('Processing...');
        $progressBar = new ProgressBar($output, $totalLines);
        $progressBar->start();

        $converted = 0;
        $errors = 0;

        foreach ($this->csvService->readCsv($this->inputFileSocial) as $item) {
            $row = $item['row'];

            // Get MAJIC code
            $majicCode = trim($row[$colIndexCodeParcelle], '"');

            // Convert to CNIG
            $cnigCode = $this->majicConverter->convertToCnigPci($majicCode);

            if (!empty($cnigCode)) {
                $converted++;
            } else {
                $errors++;
            }

            // Update or add id_parcellaire column
            if (count($row) > $colIndexIdParcellaire) {
                $row[$colIndexIdParcellaire] = $cnigCode;
            } else {
                $row[] = $cnigCode;
            }

            // Write row
            fputcsv($outHandle, $row, ';', '"', '\\');

            $progressBar->advance();
        }

        fclose($outHandle);
        $progressBar->finish();
        $io->newLine(2);

        // Replace original file
        if (!rename($tempFile, $this->inputFileSocial)) {
            $io->error('Cannot replace original file');
            return Command::FAILURE;
        }

        // Display summary
        $io->section('Processing complete');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Lines processed', $totalLines],
                ['Successful conversions', $converted],
                ['Conversion errors', $errors],
            ]
        );

        $io->success("File updated: {$this->inputFileSocial}");
        $io->info("Backup: $backupFile");

        // Display sample conversions
        $io->section('Sample conversions:');
        $sampleCount = 0;
        foreach ($this->csvService->readCsv($this->inputFileSocial) as $item) {
            if ($sampleCount >= 3) {
                break;
            }

            $row = $item['row'];
            $header = $item['header'];

            $colCodeIdx = array_search($this->colCodeParcelle, $header);
            $colIdIdx = array_search($this->colIdParcellaire, $header);

            if ($colCodeIdx !== false && $colIdIdx !== false) {
                $majic = trim($row[$colCodeIdx], '"');
                $cnig = trim($row[$colIdIdx], '"');
                $io->text("MAJIC: $majic → CNIG: $cnig");
            }

            $sampleCount++;
        }

        return Command::SUCCESS;
    }
}
