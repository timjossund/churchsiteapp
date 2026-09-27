<?php

return [
    'enabled' => env('DOMAIN_PROXY_ENABLED', false),
    'secret' => env('DOMAIN_PROXY_SECRET'),
    'proof_host' => env('DOMAIN_PROXY_PROOF_HOST'),
];
