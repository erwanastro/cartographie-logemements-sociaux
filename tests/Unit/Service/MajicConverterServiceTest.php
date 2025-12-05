<?php

namespace App\Tests\Unit\Service;

use App\Service\MajicConverterService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for MajicConverterService
 * Tests MAJIC to CNIG PCI conversion logic
 */
final class MajicConverterServiceTest extends TestCase
{
    private MajicConverterService $service;

    protected function setUp(): void
    {
        $this->service = new MajicConverterService();
    }

    /**
     * Test valid MAJIC to CNIG conversion
     *
     * MAJIC Format (14 chars): DDCCCSSSSXNNNN
     * - DD = Department (2)
     * - CCC = Commune (3)
     * - SSSS = Numeric section (4)
     * - XNNNN = Sub-parcel + Parcel (5)
     *
     * CNIG PCI Format (12 chars): DDCCCSSNNNN
     * Conversion: MAJIC[0:5] + MAJIC[7:3] + MAJIC[10:4]
     *
     * @dataProvider validMajicCodesProvider
     */
    public function testConvertToCnigPciWithValidCodes(string $majic, string $expectedCnig): void
    {
        $result = $this->service->convertToCnigPci($majic);

        $this->assertSame(
            $expectedCnig,
            $result,
            "MAJIC code $majic should convert to CNIG $expectedCnig"
        );
    }

    /**
     * Test invalid MAJIC codes (wrong length)
     *
     * @dataProvider invalidMajicCodesProvider
     */
    public function testConvertToCnigPciWithInvalidCodes(string $majic): void
    {
        $result = $this->service->convertToCnigPci($majic);

        $this->assertSame(
            '',
            $result,
            "Invalid MAJIC code $majic should return empty string"
        );
    }

    /**
     * Test that CNIG result has correct length
     */
    public function testConvertToCnigPciResultLength(): void
    {
        $result = $this->service->convertToCnigPci('67482000010017');

        $this->assertSame(12, strlen($result), 'CNIG code should be 12 characters long');
    }

    /**
     * Test conversion preserves numeric format
     */
    public function testConvertToCnigPciPreservesNumericFormat(): void
    {
        $result = $this->service->convertToCnigPci('67482000010017');

        $this->assertMatchesRegularExpression(
            '/^\d{12}$/',
            $result,
            'CNIG code should contain only digits'
        );
    }

    /**
     * Provider for valid MAJIC codes
     * Based on test_process.php examples
     */
    public static function validMajicCodesProvider(): array
    {
        return [
            'Example 1 from test file' => ['67482000010017', '674820010017'],
            'Example 2 from test file' => ['67482000010018', '674820010018'],
            'Example 3 from test file' => ['67482000040014', '674820040014'],
            'Different department' => ['75101000020025', '751010020025'],
            'Different commune' => ['67999000010001', '679990010001'],
        ];
    }

    /**
     * Provider for invalid MAJIC codes
     */
    public static function invalidMajicCodesProvider(): array
    {
        return [
            'Too short' => ['123456789012'],
            'Too long' => ['123456789012345'],
            'Empty string' => [''],
            'Much too short' => ['1234'],
            'Single char' => ['1'],
        ];
    }

    /**
     * Test boundary conditions
     */
    public function testConvertToCnigPciBoundaryConditions(): void
    {
        // Test with exactly 14 characters
        $result = $this->service->convertToCnigPci('12345678901234');
        $this->assertNotEmpty($result, '14 character code should be valid');

        // Test with 13 characters
        $result = $this->service->convertToCnigPci('1234567890123');
        $this->assertEmpty($result, '13 character code should be invalid');

        // Test with 15 characters
        $result = $this->service->convertToCnigPci('123456789012345');
        $this->assertEmpty($result, '15 character code should be invalid');
    }

    /**
     * Test conversion formula
     * Verifies that the conversion follows the exact formula:
     * CNIG = substr(MAJIC, 0, 5) + substr(MAJIC, 7, 3) + substr(MAJIC, 10, 4)
     */
    public function testConversionFormula(): void
    {
        $majic = '67482000010017';
        $result = $this->service->convertToCnigPci($majic);

        $expectedPart1 = substr($majic, 0, 5);  // '67482'
        $expectedPart2 = substr($majic, 7, 3);  // '001'
        $expectedPart3 = substr($majic, 10, 4); // '0017'
        $expectedCnig = $expectedPart1 . $expectedPart2 . $expectedPart3;

        $this->assertSame($expectedCnig, $result, 'Conversion should follow the formula');
        $this->assertSame('67482', substr($result, 0, 5), 'First 5 chars should be department+commune');
        $this->assertSame('001', substr($result, 5, 3), 'Next 3 chars should be section');
        $this->assertSame('0017', substr($result, 8, 4), 'Last 4 chars should be parcel');
    }
}
