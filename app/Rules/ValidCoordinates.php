<?php

namespace App\Rules;

use App\Support\CoordinatePair;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use InvalidArgumentException;

final class ValidCoordinates implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Koordinat harus diisi sebagai teks dengan format latitude, longitude.');

            return;
        }

        try {
            CoordinatePair::parse($value);
        } catch (InvalidArgumentException $exception) {
            $fail($exception->getMessage());
        }
    }
}
