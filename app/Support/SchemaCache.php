<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

final class SchemaCache
{
    public static function clear(): void
    {
        $schemaPath = base_path('bootstrap/cache/schema.php');

        if (is_file($schemaPath)) {
            @unlink($schemaPath);
        }

        try {
            Artisan::call('optimize:clear');
        } catch (\Throwable) {
            // optimize:clear may be unavailable in some environments.
        }
    }
}
