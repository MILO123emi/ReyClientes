<?php

use App\Support\Phone;

it('normalizes and formats local and international phones', function (string $input) {
    expect(Phone::normalize($input))->toBe('99999999');
    expect(Phone::format($input))->toBe('9999-9999');
})->with(['99999999', '9999-9999', '+504 9999-9999', '504 9999-9999', ' 9999 9999 ']);

it('keeps a local number starting with 504 intact', function () {
    expect(Phone::normalize('5041-2345'))->toBe('50412345');
});
