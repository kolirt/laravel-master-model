<?php

use Illuminate\Support\Str;

if (!function_exists('is_stored_file')) {
    function is_stored_file(mixed $value): bool
    {
        return
            is_string($value) &&
            Str::of($value)->startsWith(
                array_map(fn($value) => "$value:", array_keys(config('filesystems.disks')))
            );
    }
}
