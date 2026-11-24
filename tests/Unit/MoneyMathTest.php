<?php

declare(strict_types=1);

use App\Services\MoneyMath;

it('performs high-precision addition without floating-point errors', function () {
    expect(MoneyMath::add('0.10', '0.20'))->toBe('0.30')
        ->and(MoneyMath::add('125000.55', '4500.45'))->toBe('129501.00')
        ->and(MoneyMath::add(10, 25))->toBe('35.00');
});

it('performs exact subtraction and respects non-negative flooring when needed', function () {
    expect(MoneyMath::sub('100.00', '45.50'))->toBe('54.50')
        ->and(MoneyMath::max('0.00', MoneyMath::sub('50.00', '75.00')))->toBe('0.00');
});

it('calculates percentages and tax amounts accurately without drift', function () {
    // 15% VAT on 1,500.00 = 225.00
    expect(MoneyMath::percent('1500.00', '15'))->toBe('225.00')
        // 7.5% tax on 333.33 = 25.00 (with scale 2)
        ->and(MoneyMath::percent('333.33', '7.5'))->toBe('25.00');
});

it('compares monetary amounts correctly', function () {
    expect(MoneyMath::gt('100.01', '100.00'))->toBeTrue()
        ->and(MoneyMath::gte('100.00', '100.00'))->toBeTrue()
        ->and(MoneyMath::lt('99.99', '100.00'))->toBeTrue()
        ->and(MoneyMath::eq('50.50', '50.50'))->toBeTrue()
        ->and(MoneyMath::eq('50.50', '50.51'))->toBeFalse();
});

it('cleans formatted money strings containing commas, currency symbols, and spaces', function () {
    expect(MoneyMath::clean('৳ 1,250.75'))->toBe('1250.75')
        ->and(MoneyMath::clean('  500.00 '))->toBe('500.00')
        ->and(MoneyMath::clean(null))->toBe('0.00')
        ->and(MoneyMath::clean(''))->toBe('0.00');
});
