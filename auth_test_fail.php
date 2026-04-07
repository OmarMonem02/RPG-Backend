<?php

use App\Services\Auth\LoginService;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
try {
    app(LoginService::class)->execute([
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ]);
    echo 'LOGIN_OK';
} catch (Throwable $e) {
    echo get_class($e);
}
