<?php

namespace Tests\Unit;

use App\Support\Decimal as D;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ManufacturingDecimalTest extends TestCase
{
    public function test_money_and_quantities_are_calculated_without_binary_float_error(): void
    {
        $this->assertSame('0.3000', D::add('0.1', '0.2'));
        $this->assertSame('999999999999999999.9999', D::add('999999999999999999.9998', '0.0001'));
        $this->assertSame('1.2346', D::value('1.23455'));
        $this->assertSame('-1.2346', D::value('-1.23455'));
        $this->assertSame('80.0000', D::div('8000', '100'));
    }

    /** @dataProvider invalidDecimals */
    public function test_ambiguous_or_lossy_financial_input_is_rejected($input): void
    {
        $this->expectException(ValidationException::class); D::value($input);
    }

    public function invalidDecimals(): array { return [[0.2], ['1e3'], ['1,000'], ['NaN'], ['9999999999999999999']]; }
}
