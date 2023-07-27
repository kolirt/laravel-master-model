<?php

use Illuminate\Support\Arr;

if (!function_exists('array_only')) {
    function array_only($array, $keys)
    {
        return Arr::only($array, $keys);
    }
}
