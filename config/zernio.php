<?php

return [
    'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com/api/v1'),
    'api_key' => env('ZERNIO_API_KEY'),
    'account_id' => env('ZERNIO_ACCOUNT_ID'),
    'profile_id' => env('ZERNIO_PROFILE_ID'),
    'username' => env('ZERNIO_USERNAME'),
    'timeout' => (int) env('ZERNIO_TIMEOUT', 30),
];
