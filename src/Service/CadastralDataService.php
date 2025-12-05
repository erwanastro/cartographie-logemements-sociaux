<?php

namespace App\Service;

/**
 * Service for cadastral data operations
 * Single Responsibility: Load cadastral data
 */
final readonly class CadastralDataService
{
    public function __construct(
        private CsvService $csvService
    ) {}

    /**
     * Load cadastral data indexed by id_parcellaire (CNIG PCI)
     */
    public function loadCadastralData(
        string $filename,
        string $colIdParcellaire,
        string $colGeoShape,
        string $colLat,
        string $colLon
    ): array {
        $data = [];
        $header = $this->csvService->getHeader($filename);

        $colIndexId = array_search($colIdParcellaire, $header);
        $colIndexGeo = array_search($colGeoShape, $header);
        $colIndexLat = array_search($colLat, $header);
        $colIndexLon = array_search($colLon, $header);

        if ($colIndexId === false || $colIndexGeo === false) {
            throw new \RuntimeException(
                "Required columns ($colIdParcellaire, $colGeoShape) not found in $filename"
            );
        }

        foreach ($this->csvService->readCsv($filename) as $item) {
            $row = $item['row'];
            $id = trim($row[$colIndexId], ' "');
            $geo = $row[$colIndexGeo];

            if (empty($id) || empty($geo)) {
                continue;
            }

            $data[$id] = [
                'geo' => $geo,
                'lat' => $colIndexLat !== false ? $row[$colIndexLat] : null,
                'lon' => $colIndexLon !== false ? $row[$colIndexLon] : null
            ];
        }

        return $data;
    }
}
