<?php

namespace App\Services;

use App\Models\BikeForSale;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SparePart;
use Illuminate\Database\Eloquent\Model;

class CatalogPricingService
{
    /**
     * @return array{usd: float, eur: float}
     */
    public function exchangeRates(): array
    {
        $settings = Setting::query()
            ->whereIn('key', ['exchange_rate', 'exchange_rate_eur'])
            ->pluck('value', 'key');

        return [
            'usd' => (float) ($settings->get('exchange_rate') ?: 1),
            'eur' => (float) ($settings->get('exchange_rate_eur') ?: 1),
        ];
    }

    public function multiplierForCurrency(string $currency, ?array $rates = null): float
    {
        $rates ??= $this->exchangeRates();

        return match (strtoupper($currency)) {
            'USD' => $rates['usd'] > 0 ? $rates['usd'] : 1.0,
            'EUR' => $rates['eur'] > 0 ? $rates['eur'] : 1.0,
            default => 1.0,
        };
    }

    public function costInEgp(float $costPrice, string $costCurrency, ?array $rates = null): float
    {
        return round($costPrice * $this->multiplierForCurrency($costCurrency, $rates), 2);
    }

    public function saleInEgp(float $salePrice, string $saleCurrency, ?array $rates = null): float
    {
        return round($salePrice * $this->multiplierForCurrency($saleCurrency, $rates), 2);
    }

    public function calculateSaleFromMargin(
        float $costPrice,
        string $costCurrency,
        string $marginType,
        float $marginValue,
        string $saleCurrency,
        ?array $rates = null,
    ): float {
        $costEgp = $this->costInEgp($costPrice, $costCurrency, $rates);

        $saleEgp = match ($marginType) {
            'percentage' => round($costEgp * (1 + ($marginValue / 100)), 2),
            'fixed' => round($costEgp + $marginValue, 2),
            default => $costEgp,
        };

        $saleMultiplier = $this->multiplierForCurrency($saleCurrency, $rates);

        return $saleMultiplier > 0
            ? round($saleEgp / $saleMultiplier, 2)
            : $saleEgp;
    }

    /**
     * @param  Product|SparePart|BikeForSale  $item
     */
    public function isPricingLoss(Model $item, ?array $rates = null): bool
    {
        $costEgp = $this->costInEgp(
            (float) $item->cost_price,
            (string) ($item->cost_currency ?? 'EGP'),
            $rates,
        );

        $saleEgp = $this->saleInEgp(
            (float) $item->sale_price,
            (string) ($item->sale_currency ?? 'EGP'),
            $rates,
        );

        return $costEgp > $saleEgp;
    }

    /**
     * @param  Product|SparePart|BikeForSale  $item
     */
    public function suggestSalePrice(Model $item, ?array $rates = null): float
    {
        $costCurrency = (string) ($item->cost_currency ?? 'EGP');
        $saleCurrency = (string) ($item->sale_currency ?? 'EGP');
        $costPrice = (float) $item->cost_price;

        if (($item->sale_price_mode ?? 'manual') === 'margin'
            && $item->sale_margin_type
            && $item->sale_margin_value !== null) {
            return $this->calculateSaleFromMargin(
                $costPrice,
                $costCurrency,
                (string) $item->sale_margin_type,
                (float) $item->sale_margin_value,
                $saleCurrency,
                $rates,
            );
        }

        $rates ??= $this->exchangeRates();
        $costEgp = $this->costInEgp($costPrice, $costCurrency, $rates);
        $saleEgp = $this->saleInEgp((float) $item->sale_price, $saleCurrency, $rates);

        if ($costEgp <= 0) {
            return (float) $item->sale_price;
        }

        $preservedMarginPercent = (($saleEgp - $costEgp) / $costEgp) * 100;

        return $this->calculateSaleFromMargin(
            $costPrice,
            $costCurrency,
            'percentage',
            max(0, $preservedMarginPercent),
            $saleCurrency,
            $rates,
        );
    }

    /**
     * @param  Product|SparePart|BikeForSale  $item
     */
    public function normalizeModel(Model $item): void
    {
        if (! $item->cost_currency) {
            $item->cost_currency = (string) ($item->sale_currency ?? 'EGP');
        }

        if (! $item->sale_currency) {
            $item->sale_currency = (string) ($item->cost_currency ?? 'EGP');
        }

        if (($item->sale_price_mode ?? 'manual') === 'margin') {
            $item->sale_price = $this->calculateSaleFromMargin(
                (float) $item->cost_price,
                (string) $item->cost_currency,
                (string) $item->sale_margin_type,
                (float) $item->sale_margin_value,
                (string) $item->sale_currency,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function itemToAlarmPayload(Model $item, string $itemType, ?array $rates = null): array
    {
        $rates ??= $this->exchangeRates();
        $costCurrency = (string) ($item->cost_currency ?? 'EGP');
        $saleCurrency = (string) ($item->sale_currency ?? 'EGP');
        $costEgp = $this->costInEgp((float) $item->cost_price, $costCurrency, $rates);
        $saleEgp = $this->saleInEgp((float) $item->sale_price, $saleCurrency, $rates);
        $suggested = $this->suggestSalePrice($item, $rates);

        $name = match ($itemType) {
            'bike' => trim(($item->bikeBlueprint?->brand?->name ?? '').' '.($item->bikeBlueprint?->model ?? '')).' ('.$item->vin.')',
            default => (string) $item->name,
        };

        $sku = match ($itemType) {
            'bike' => (string) $item->vin,
            default => (string) ($item->sku ?? ''),
        };

        return [
            'item_type' => $itemType,
            'id' => $item->id,
            'name' => $name,
            'sku' => $sku,
            'cost_price' => (float) $item->cost_price,
            'cost_currency' => $costCurrency,
            'sale_price' => (float) $item->sale_price,
            'sale_currency' => $saleCurrency,
            'cost_egp' => $costEgp,
            'sale_egp' => $saleEgp,
            'loss_amount_egp' => round(max(0, $costEgp - $saleEgp), 2),
            'suggested_sale_price' => $suggested,
            'sale_price_mode' => $item->sale_price_mode ?? 'manual',
            'sale_margin_type' => $item->sale_margin_type,
            'sale_margin_value' => $item->sale_margin_value !== null ? (float) $item->sale_margin_value : null,
        ];
    }
}
