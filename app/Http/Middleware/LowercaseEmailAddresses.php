<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Support\Str;

class LowercaseEmailAddresses extends TransformsRequest
{
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function transform($key, $value): mixed
    {
        if ($key !== 'email') {
            return $value;
        }

        return is_string($value) ? Str::lower($value) : $value;
    }
}
