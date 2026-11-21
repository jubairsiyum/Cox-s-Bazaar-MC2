<?php

use App\Helpers\Money;
use App\Models\Setting;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('renders the BDT formatter in a blade template', function () {
    $rendered = Blade::render('@bdt($amount)', ['amount' => '3358501.50']);

    expect($rendered)->toBe('৳3,358,501.50');
});

it('returns the taka symbol in UTF-8 JSON', function () {
    Route::get('/_test/currency-encoding', fn () => response()->json([
        'amount' => Money::format('125.75'),
    ]));

    $response = $this->getJson('/_test/currency-encoding');

    $response->assertOk()
        ->assertHeader('content-type', 'application/json')
        ->assertJson(['amount' => '৳125.75']);
});

it('round trips the taka symbol through database settings', function () {
    Setting::set('currency_symbol', '৳');

    expect(Setting::get('currency_symbol'))->toBe('৳');
});
