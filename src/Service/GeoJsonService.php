<?php

namespace App\Service;

/**
 * Service for GeoJSON operations
 * Single Responsibility: GeoJSON creation and manipulation
 */
final readonly class GeoJsonService
{
    /**
     * Create a Point geometry from GPS coordinates
     */
    public function createPointGeometry(string $gpsCoords): string
    {
        $coords = explode(',', $gpsCoords);

        if (count($coords) !== 2) {
            return '';
        }

        $lat = floatval(trim($coords[0]));
        $lon = floatval(trim($coords[1]));

        $point = [
            'type' => 'Point',
            'coordinates' => [$lon, $lat]
        ];

        return json_encode($point);
    }

    /**
     * Extract coordinates from GeoJSON string
     */
    public function extractCoordinates(string $geoJson): ?array
    {
        $decoded = json_decode($geoJson, true);

        if (!isset($decoded['coordinates']) || !is_array($decoded['coordinates'])) {
            return null;
        }

        // For Polygon, coordinates is an array of rings
        if ($decoded['type'] === 'Polygon' && !empty($decoded['coordinates'][0])) {
            // Take first point of first ring
            $firstPoint = $decoded['coordinates'][0][0];
            if (is_array($firstPoint) && count($firstPoint) >= 2) {
                return [
                    'lon' => floatval($firstPoint[0]),
                    'lat' => floatval($firstPoint[1])
                ];
            }
        }

        return null;
    }

    /**
     * Create a GeoJSON Feature
     */
    public function createFeature(array $geometry, array $properties): array
    {
        return [
            'type' => 'Feature',
            'geometry' => $geometry,
            'properties' => $properties
        ];
    }

    /**
     * Create a GeoJSON FeatureCollection
     */
    public function createFeatureCollection(array $features): array
    {
        return [
            'type' => 'FeatureCollection',
            'features' => $features
        ];
    }

    /**
     * Write GeoJSON to file
     */
    public function writeGeoJson(string $filename, array $geoJson): void
    {
        $jsonString = json_encode($geoJson, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($jsonString === false) {
            throw new \RuntimeException("Cannot encode GeoJSON");
        }

        if (file_put_contents($filename, $jsonString) === false) {
            throw new \RuntimeException("Cannot write GeoJSON file: $filename");
        }
    }
}
