<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->json('types')->nullable()->after('name');
        });

        $brands = DB::table('brands')->orderBy('id')->get();
        $groups = [];

        foreach ($brands as $brand) {
            $key = strtolower(trim((string) $brand->name));

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'keep_id' => $brand->id,
                    'types' => [$brand->type],
                    'duplicate_ids' => [],
                ];

                continue;
            }

            $groups[$key]['types'][] = $brand->type;
            $groups[$key]['duplicate_ids'][] = $brand->id;
        }

        foreach ($groups as $group) {
            $types = array_values(array_unique($group['types']));

            DB::table('brands')
                ->where('id', $group['keep_id'])
                ->update(['types' => json_encode($types)]);

            foreach ($group['duplicate_ids'] as $duplicateId) {
                DB::table('products')->where('brand_id', $duplicateId)->update(['brand_id' => $group['keep_id']]);
                DB::table('spare_parts')->where('brand_id', $duplicateId)->update(['brand_id' => $group['keep_id']]);
                DB::table('bike_blueprints')->where('brand_id', $duplicateId)->update(['brand_id' => $group['keep_id']]);

                DB::table('brands')
                    ->where('id', $duplicateId)
                    ->update(['deleted_at' => now()]);
            }
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->enum('type', ['spare_parts', 'products', 'bikes'])->nullable()->after('name');
            $table->index('type');
        });

        $brands = DB::table('brands')->whereNull('deleted_at')->orderBy('id')->get();

        foreach ($brands as $brand) {
            $types = json_decode((string) $brand->types, true);
            $primaryType = is_array($types) && $types !== [] ? $types[0] : 'products';

            DB::table('brands')
                ->where('id', $brand->id)
                ->update(['type' => $primaryType]);
        }

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('types');
        });
    }
};
