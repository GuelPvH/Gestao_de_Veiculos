<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    /** @return array<string, array{string, string, string}> */
    public static function lines(): array
    {
        return [
            'meio centavo arredonda para cima' => ['2.500', '10.35', '25.88'],
            'abaixo de meio centavo arredonda para baixo' => ['0.333', '0.01', '0.00'],
            'exatamente meio centavo' => ['0.5', '0.01', '0.01'],
            'quantidade inteira' => ['3', '10.35', '31.05'],
            'valor zero' => ['4', '0', '0.00'],
            'valores grandes sem perda de precisão' => ['999999.999', '9999999.99', '9999999980000.00'],
        ];
    }

    #[DataProvider('lines')]
    public function test_line_total_rounds_half_up_to_the_cent(string $quantity, string $unitValue, string $expected): void
    {
        $this->assertSame($expected, Money::lineTotal($quantity, $unitValue));
    }

    public function test_sums_and_differences_are_exact_and_keep_two_decimals(): void
    {
        $this->assertSame('0.30', Money::sum(['0.1', '0.20']), 'Sem a imprecisão de 0.1 + 0.2 em ponto flutuante.');
        $this->assertSame('0.00', Money::sum([]));
        $this->assertSame('8.95', Money::difference('140.00', '131.05'));
        $this->assertSame('-0.05', Money::difference('1.00', '1.05'));
    }

    public function test_limit_matches_the_expense_column(): void
    {
        $this->assertFalse(Money::exceedsLimit('99999999999.99'));
        $this->assertTrue(Money::exceedsLimit('100000000000.00'));
    }

    #[DataProvider('invalid')]
    public function test_malformed_decimals_are_refused(string $quantity, string $unitValue): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::lineTotal($quantity, $unitValue);
    }

    /** @return array<string, array{string, string}> */
    public static function invalid(): array
    {
        return [
            'letras' => ['abc', '1.00'],
            'negativo' => ['1', '-1.00'],
            'casas demais no valor' => ['1', '1.001'],
            'casas demais na quantidade' => ['1.0001', '1.00'],
            'notação científica' => ['1e3', '1.00'],
            'vazio' => ['', '1.00'],
        ];
    }
}
