<?php

namespace App\Console\Commands;

use App\Support\ApiCache;
use Illuminate\Console\Command;

class ClearApiCache extends Command
{
    protected $signature = 'cache:clear-api {entity? : spare-parts|products|brands|bikes|blueprints|services|all}';

    protected $description = 'Clear API cache by entity tag or for all supported entities.';

    /**
     * @var array<string, string>
     */
    private array $entityTagMap = [
        'spare-parts' => 'spare_parts',
        'products' => 'products',
        'brands' => 'brands',
        'bikes' => 'bikes',
        'blueprints' => 'blueprints',
        'services' => 'services',
    ];

    public function handle(): int
    {
        $entity = (string) ($this->argument('entity') ?? 'all');

        if ($entity === 'all') {
            ApiCache::invalidateTags(array_values($this->entityTagMap));
            $this->info('API cache cleared for entity: all');

            return self::SUCCESS;
        }

        $tag = $this->entityTagMap[$entity] ?? null;
        if ($tag === null) {
            $this->error('Invalid entity. Supported: spare-parts, products, brands, bikes, blueprints, services, all');
            return self::FAILURE;
        }

        ApiCache::invalidateTags([$tag]);
        $this->info("API cache cleared for entity: {$entity}");

        return self::SUCCESS;
    }
}
