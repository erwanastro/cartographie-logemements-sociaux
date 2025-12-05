<?php

namespace App\Service;

/**
 * Service for converting MAJIC codes to CNIG PCI format
 *
 * The French cadastral system uses two different parcel identification systems:
 * - MAJIC (Mise à Jour des Informations Cadastrales): legacy alphanumeric system
 * - CNIG PCI (Plan Cadastral Informatisé): modern standardized system
 *
 * This service converts between these two formats to enable data matching between
 * social housing databases (using MAJIC) and cadastral geometries (using CNIG PCI).
 */
final readonly class MajicConverterService
{
    /**
     * Convert a MAJIC parcel code to CNIG PCI format
     *
     * MAJIC Structure (14 characters): DDCCCSSSSXNNNN
     * - DD    = INSEE department code (2 digits)     Example: 67 (Bas-Rhin)
     * - CCC   = INSEE commune code (3 digits)        Example: 482 (Strasbourg)
     * - SSSS  = Numeric section (4 digits)           Example: 0000
     * - X     = Section number (1 digit)             Example: 1
     * - NNNN  = Parcel number (4 digits)             Example: 0017
     * Full example: 67482000010017
     *
     * CNIG PCI Structure (12 characters): DDCCCSSNNNN
     * - DDDCC = Department + commune (5 chars)       Example: 67482
     * - SS    = Section (2-3 chars, numeric or letters) Example: 001 or LC
     * - NNNN  = Parcel number (4 digits)             Example: 0017
     * Full example: 674820010017 (numeric section) or 67482LC0017 (letter section)
     *
     * Conversion Formula for Numeric Sections:
     * CNIG = MAJIC[0:5] + MAJIC[7:3] + MAJIC[10:4]
     *        ^^^^^^^^     ^^^^^^^^^^   ^^^^^^^^^^^
     *        DEPT+COMM    SECTION      PARCEL
     *
     * Example conversion:
     * MAJIC:  67482000010017
     *         |___||_____||____|
     *         67482  001   0017
     * CNIG:   674820010017
     *
     * Note: Some parcels use letter sections (A, B, LC, EB, etc.) which are stored
     * in a separate column (N_SECTION) in the cadastral data and require special handling.
     *
     * @param string $majicCode The 14-character MAJIC code
     * @return string The 12-character CNIG PCI code, or empty string if invalid format
     */
    public function convertToCnigPci(string $majicCode): string
    {
        // Validate format: MAJIC codes must be exactly 14 characters
        if (strlen($majicCode) !== 14) {
            return '';
        }

        // Extract and concatenate the three parts:
        // - Positions 0-4: Department + Commune (5 chars)
        // - Positions 7-9: Section number (3 chars, from SSSSXNNNN we take X and part of SSSS)
        // - Positions 10-13: Parcel number (4 chars)
        return substr($majicCode, 0, 5) . substr($majicCode, 7, 3) . substr($majicCode, 10, 4);
    }
}
