<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fiuu Payment Gateway Hash Algorithm
    |--------------------------------------------------------------------------
    |
    | Only "sha512" is supported. Uses plain SHA-512 hashing.
    |
    */

    'hash_algo' => env('FIUU_HASH_ALGO', 'sha512'),
];
