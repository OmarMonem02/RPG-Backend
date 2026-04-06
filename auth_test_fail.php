<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    app(App\Services\Auth\LoginService::class)->execute([
        'email' => 'admin@example.com',
        'password' => 'wrong',
    ]);
    echo 'LOGIN_OK';
} catch (Throwable $e) {
    echo get_class($e);
}
