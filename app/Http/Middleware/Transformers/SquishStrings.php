<?php

declare(strict_types=1);

namespace App\Http\Middleware\Transformers;

use Illuminate\Foundation\Http\Middleware\TransformsRequest;
use Illuminate\Support\Str;

class SquishStrings extends TransformsRequest
{
    /**
     * Transform the given value.
     *
     * @param  string  $key
     * @param  mixed  $value
     */
    protected function transform($key, $value): mixed
    {
        return is_string($value) ? Str::squish($value) : $value;
    }
}
