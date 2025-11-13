<?php

// return [
//     'paths' => ['api/*'], // API routes to apply CORS to
//     'allowed_methods' => ['*'], // Allow all methods (GET, POST, PUT, DELETE, etc.)
//     'allowed_origins' => ['https://wapp.smsforyou.biz'], // Allowed origin (replace with your frontend domain)
//     'allowed_origins_patterns' => [],
//     'allowed_headers' => ['*'], // Allow all headers
//     'exposed_headers' => [],
//     'supports_credentials' => false, // Set to true if your API uses cookies or other credentials
//     'max_age' => 0, // Cache preflight requests for 0 seconds
// ];

return [
    'paths' => ['*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
