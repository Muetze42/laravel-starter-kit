<?php

namespace App\Http\Middleware;

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
