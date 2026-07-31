<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /**
     * @return array<int, mixed>
     */
    public static function rules(bool $required = true): array
    {
        return [
            $required ? 'required' : 'nullable',
            'string',
            Password::min(self::MIN_LENGTH)
                ->mixedCase()
                ->numbers()
                ->symbols(),
            'confirmed',
        ];
    }
}
