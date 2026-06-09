<?php

namespace App\Support;

use App\Models\ApprovalRequest;
use App\Models\BikeForSale;
use App\Models\MaintenanceService;
use App\Models\Product;
use App\Models\SparePart;
use App\Models\User;
use App\Services\ApprovalRequestService;
use Illuminate\Validation\ValidationException;

final class ItemDiscountResolver
{
    /**
     * @return array{0: ?string, 1: float}
     */
    public static function catalogMaxDiscount(
        ?int $productId,
        ?int $sparePartId,
        ?int $maintenanceServiceId,
        ?int $bikeForSaleId = null,
    ): array {
        if ($sparePartId) {
            $part = SparePart::query()->find($sparePartId);
            if ($part) {
                return [$part->max_discount_type, (float) $part->max_discount_value];
            }
        }

        if ($productId) {
            $product = Product::query()->find($productId);
            if ($product) {
                return [$product->max_discount_type, (float) $product->max_discount_value];
            }
        }

        if ($maintenanceServiceId) {
            $service = MaintenanceService::query()->find($maintenanceServiceId);
            if ($service) {
                return [$service->max_discount_type, (float) $service->max_discount_value];
            }
        }

        if ($bikeForSaleId) {
            $bike = BikeForSale::query()->find($bikeForSaleId);
            if ($bike) {
                return [$bike->max_discount_type, (float) $bike->max_discount_value];
            }
        }

        return [null, 0.0];
    }

    public static function resolveUnitDiscount(
        ?User $user,
        float $requestedUnitDiscount,
        float $unitPrice,
        ?int $productId = null,
        ?int $sparePartId = null,
        ?int $maintenanceServiceId = null,
        ?int $bikeForSaleId = null,
        ?int $approvalRequestId = null,
        ?int $consumedTicketId = null,
    ): float {
        $unitPrice = max(0.0, $unitPrice);
        $requestedUnitDiscount = max(0.0, $requestedUnitDiscount);

        if ($requestedUnitDiscount > $unitPrice) {
            throw ValidationException::withMessages([
                'discount' => ['Item discount cannot exceed the unit price.'],
            ]);
        }

        if ($user?->role === User::ROLE_TECHNICIAN && $requestedUnitDiscount > 0) {
            throw ValidationException::withMessages([
                'discount' => ['Only staff can apply line discounts on maintenance tickets.'],
            ]);
        }

        if (! $user || $user->role !== User::ROLE_STAFF) {
            return $requestedUnitDiscount;
        }

        [$maxType, $maxValue] = self::catalogMaxDiscount(
            $productId,
            $sparePartId,
            $maintenanceServiceId,
            $bikeForSaleId,
        );
        $allowedUnit = MaxDiscount::maxLineDiscount($unitPrice, $maxType, $maxValue);

        if ($requestedUnitDiscount <= $allowedUnit + 0.00001) {
            return $requestedUnitDiscount;
        }

        if (! $approvalRequestId) {
            throw ValidationException::withMessages([
                'discount' => [
                    sprintf(
                        'Staff cannot apply more than %s discount on this item without admin approval.',
                        number_format($allowedUnit, 2, '.', ''),
                    ),
                ],
            ]);
        }

        $approval = ApprovalRequest::query()->find($approvalRequestId);
        if (! $approval) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['Discount approval request was not found.'],
            ]);
        }

        $expectedType = $consumedTicketId !== null
            ? ApprovalRequest::TYPE_TICKET_ITEM_DISCOUNT
            : ApprovalRequest::TYPE_SALE_ITEM_DISCOUNT;

        if ($approval->type !== $expectedType) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['This approval request type does not match the item discount.'],
            ]);
        }

        if ((int) $approval->requested_by !== (int) $user->id) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['This discount approval request does not belong to you.'],
            ]);
        }

        if (! $approval->isConsumable()) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['This discount approval request is not approved or has already been used.'],
            ]);
        }

        if (round((float) $approval->approved_discount_amount, 2) !== round($requestedUnitDiscount, 2)) {
            throw ValidationException::withMessages([
                'discount' => ['Item discount must match the approved discount amount.'],
            ]);
        }

        return $requestedUnitDiscount;
    }

    public static function consumeItemApproval(
        int $approvalRequestId,
        int $userId,
        float $unitDiscountAmount,
        ?int $consumedSaleId = null,
        ?int $consumedTicketId = null,
    ): void {
        app(ApprovalRequestService::class)->consumeApprovedRequest(
            $approvalRequestId,
            $userId,
            $unitDiscountAmount,
            consumedSaleId: $consumedSaleId,
            consumedTicketId: $consumedTicketId,
        );
    }
}
