<?php

namespace App\Services;

use App\Models\BikeBlueprint;
use App\Models\BikeBlueprintSparePart;
use App\Models\SparePart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BikeBlueprintSparePartService
{
    /**
     * Assign a single spare part to a bike blueprint.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  int  $sparePartId
     * @return BikeBlueprintSparePart
     *
     * @throws QueryException if duplicate (unique constraint violation)
     */
    public function assignSparePart(BikeBlueprint $bikeBlueprint, int $sparePartId): BikeBlueprintSparePart
    {
        return DB::transaction(function () use ($bikeBlueprint, $sparePartId) {
            // Verify spare part exists
            SparePart::findOrFail($sparePartId);

            return BikeBlueprintSparePart::create([
                'bike_blueprint_id' => $bikeBlueprint->id,
                'spare_part_id' => $sparePartId,
            ]);
        });
    }

    /**
     * Assign multiple spare parts to a bike blueprint.
     * Handles duplicates gracefully by skipping them.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  array  $sparePartIds  Array of spare part IDs
     * @return array Array of created BikeBlueprintSparePart records
     */
    public function assignSpareParts(BikeBlueprint $bikeBlueprint, array $sparePartIds): array
    {
        return DB::transaction(function () use ($bikeBlueprint, $sparePartIds) {
            // Verify all spare parts exist
            $existingParts = SparePart::whereIn('id', $sparePartIds)->count();
            if ($existingParts !== count($sparePartIds)) {
                throw new \InvalidArgumentException('One or more spare parts do not exist.');
            }

            // Get existing assignments
            $existing = BikeBlueprintSparePart::where('bike_blueprint_id', $bikeBlueprint->id)
                ->whereIn('spare_part_id', $sparePartIds)
                ->pluck('spare_part_id')
                ->toArray();

            // Filter out duplicates
            $toAssign = array_diff($sparePartIds, $existing);

            $created = [];
            foreach ($toAssign as $sparePartId) {
                $created[] = BikeBlueprintSparePart::create([
                    'bike_blueprint_id' => $bikeBlueprint->id,
                    'spare_part_id' => $sparePartId,
                ]);
            }

            return $created;
        });
    }

    /**
     * Create a new spare part and assign it to a bike blueprint in one transaction.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  array  $sparePartData  Data for creating the spare part
     * @return BikeBlueprintSparePart
     */
    public function assignAndCreateSparePart(BikeBlueprint $bikeBlueprint, array $sparePartData): BikeBlueprintSparePart
    {
        return DB::transaction(function () use ($bikeBlueprint, $sparePartData) {
            // Create the spare part
            $sparePart = SparePart::create($sparePartData);

            // Assign to blueprint
            return BikeBlueprintSparePart::create([
                'bike_blueprint_id' => $bikeBlueprint->id,
                'spare_part_id' => $sparePart->id,
            ]);
        });
    }

    /**
     * Remove a single spare part assignment from a bike blueprint.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  int  $sparePartId
     * @return bool Whether the deletion was successful
     */
    public function removeSparePart(BikeBlueprint $bikeBlueprint, int $sparePartId): bool
    {
        return DB::transaction(function () use ($bikeBlueprint, $sparePartId) {
            return (bool) BikeBlueprintSparePart::where('bike_blueprint_id', $bikeBlueprint->id)
                ->where('spare_part_id', $sparePartId)
                ->delete();
        });
    }

    /**
     * Replace all spare part assignments for a bike blueprint.
     * Deletes existing assignments and creates new ones.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  array  $sparePartIds  Array of spare part IDs
     * @return array Array of created BikeBlueprintSparePart records
     */
    public function reassignSpareParts(BikeBlueprint $bikeBlueprint, array $sparePartIds): array
    {
        return DB::transaction(function () use ($bikeBlueprint, $sparePartIds) {
            // Delete all existing assignments
            BikeBlueprintSparePart::where('bike_blueprint_id', $bikeBlueprint->id)->delete();

            // Assign new spare parts
            return $this->assignSpareParts($bikeBlueprint, $sparePartIds);
        });
    }

    /**
     * Get all spare parts for a bike blueprint with optional filtering.
     *
     * @param  BikeBlueprint  $bikeBlueprint
     * @param  array  $filters  Optional filters (category_id, brand_id, etc.)
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getSparePartsByBlueprint(BikeBlueprint $bikeBlueprint, array $filters = [], int $perPage = 15)
    {
        $query = $bikeBlueprint->spareParts()
            ->with('sparePart.category', 'sparePart.brand');

        if (! empty($filters['category_id'])) {
            $query->whereHas('sparePart', function ($q) use ($filters) {
                $q->where('spare_parts_category_id', $filters['category_id']);
            });
        }

        if (! empty($filters['brand_id'])) {
            $query->whereHas('sparePart', function ($q) use ($filters) {
                $q->where('brand_id', $filters['brand_id']);
            });
        }

        if (! empty($filters['search'])) {
            $query->whereHas('sparePart', function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('sku', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->paginate($perPage);
    }
}
