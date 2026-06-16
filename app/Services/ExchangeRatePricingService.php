<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\Product;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ExchangeRatePricingService
{
    public function __construct(
        private readonly CatalogPricingService $pricing,
    ) {}

    /**
     * @return array{usd: float, eur: float}
     */
    public function ratesFromSettings(): array
    {
        return $this->pricing->exchangeRates();
    }

    /**
     * @param  array{usd: float, eur: float}  $oldRates
     * @param  array{usd: float, eur: float}  $newRates
     * @return array{
     *     margin_items_to_update: int,
     *     manual_loss_items: array<int, array<string, mixed>>,
     *     margin_updated_items: array<int, array<string, mixed>>
     * }
     */
    public function previewRateChange(array $oldRates, array $newRates): array
    {
        $marginUpdated = [];
        $manualLoss = [];

        foreach ($this->allPricedItems() as [$item, $type]) {
            if (($item->sale_price_mode ?? 'manual') === 'margin') {
                $oldSale = (float) $item->sale_price;
                $newSale = $this->pricing->calculateSaleFromMargin(
                    (float) $item->cost_price,
                    (string) $item->cost_currency,
                    (string) $item->sale_margin_type,
                    (float) $item->sale_margin_value,
                    (string) $item->sale_currency,
                    $newRates,
                );

                if (abs($oldSale - $newSale) >= 0.01) {
                    $marginUpdated[] = array_merge(
                        $this->pricing->itemToAlarmPayload($item, $type, $newRates),
                        ['new_sale_price' => $newSale],
                    );
                }

                continue;
            }

            if ($this->pricing->isPricingLoss($item, $newRates)) {
                $manualLoss[] = $this->pricing->itemToAlarmPayload($item, $type, $newRates);
            }
        }

        return [
            'margin_items_to_update' => count($marginUpdated),
            'manual_loss_items' => $manualLoss,
            'margin_updated_items' => $marginUpdated,
        ];
    }

    /**
     * @param  array{usd: float, eur: float}  $newRates
     * @return array{
     *     margin_items_updated: int,
     *     manual_loss_items: array<int, array<string, mixed>>
     * }
     */
    public function applyRateChange(array $newRates): array
    {
        $marginUpdated = 0;
        $manualLoss = [];

        foreach ($this->allPricedItems() as [$item, $type]) {
            if (($item->sale_price_mode ?? 'manual') === 'margin') {
                $item->sale_price = $this->pricing->calculateSaleFromMargin(
                    (float) $item->cost_price,
                    (string) $item->cost_currency,
                    (string) $item->sale_margin_type,
                    (float) $item->sale_margin_value,
                    (string) $item->sale_currency,
                    $newRates,
                );
                $item->save();
                $marginUpdated++;

                continue;
            }

            if ($this->pricing->isPricingLoss($item, $newRates)) {
                $manualLoss[] = $this->pricing->itemToAlarmPayload($item, $type, $newRates);
            }
        }

        return [
            'margin_items_updated' => $marginUpdated,
            'manual_loss_items' => $manualLoss,
        ];
    }

    /**
     * @return Collection<int, array{0: Model, 1: string}>
     */
    public function allPricedItems(): Collection
    {
        $items = collect();

        Product::query()->get()->each(fn (Product $p) => $items->push([$p, 'product']));
        SparePart::query()->get()->each(fn (SparePart $p) => $items->push([$p, 'spare_part']));
        BikeForSale::query()->with('bikeBlueprint.brand')->get()->each(fn (BikeForSale $b) => $items->push([$b, 'bike']));

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listPricingAlarms(?array $rates = null): array
    {
        $rates ??= $this->pricing->exchangeRates();
        $alarms = [];

        foreach ($this->allPricedItems() as [$item, $type]) {
            if ($this->pricing->isPricingLoss($item, $rates)) {
                $alarms[] = $this->pricing->itemToAlarmPayload($item, $type, $rates);
            }
        }

        return $alarms;
    }

    /**
     * @param  array<int, array{item_type: string, id: int}>  $selections
     * @return array{updated: int, items: array<int, array<string, mixed>>}
     */
    public function applySuggestedPrices(array $selections, ?array $rates = null): array
    {
        $rates ??= $this->pricing->exchangeRates();
        $updated = [];
        $count = 0;

        foreach ($selections as $selection) {
            $item = $this->resolveItem((string) $selection['item_type'], (int) $selection['id']);
            if (! $item) {
                continue;
            }

            $item->sale_price = $this->pricing->suggestSalePrice($item, $rates);
            $item->save();
            $count++;
            $updated[] = $this->pricing->itemToAlarmPayload($item, (string) $selection['item_type'], $rates);
        }

        return ['updated' => $count, 'items' => $updated];
    }

    private function resolveItem(string $type, int $id): Product|SparePart|BikeForSale|null
    {
        return match ($type) {
            'product' => Product::query()->find($id),
            'spare_part' => SparePart::query()->find($id),
            'bike' => BikeForSale::query()->with('bikeBlueprint.brand')->find($id),
            default => null,
        };
    }
}
