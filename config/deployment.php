<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub webhook
    |--------------------------------------------------------------------------
    |
    | This shared secret is used to verify the X-Hub-Signature-256 header sent
    | by GitHub. Keep it only in .env; never commit its value.
    |
    */
    'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
];
