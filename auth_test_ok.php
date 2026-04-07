<?php

use App\Services\Auth\LoginService;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
try {
    $result = app(LoginService::class)->execute([
        'email' => 'admin@example.com',
        'password' => 'password123',
    ]);
    echo isset($result['token']) ? 'LOGIN_OK' : 'NO_TOKEN';
} catch (Throwable $e) {
    echo get_class($e).':'.$e->getMessage();
}
