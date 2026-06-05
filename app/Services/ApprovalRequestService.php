<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalRequestService
{
    public function paginate(User $actor, array $filters): LengthAwarePaginator
    {
        $query = ApprovalRequest::query()
            ->with(['requester:id,name,email', 'reviewer:id,name,email'])
            ->latest();

        if ($actor->role !== User::ROLE_ADMIN) {
            $query->where('requested_by', $actor->id);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function pendingCount(): int
    {
        return ApprovalRequest::query()
            ->where('status', ApprovalRequest::STATUS_PENDING)
            ->count();
    }

    public function findForActor(User $actor, int $id): ApprovalRequest
    {
        $request = ApprovalRequest::query()
            ->with(['requester:id,name,email', 'reviewer:id,name,email'])
            ->findOrFail($id);

        if ($actor->role !== User::ROLE_ADMIN && (int) $request->requested_by !== (int) $actor->id) {
            abort(403, 'You are not allowed to view this approval request.');
        }

        return $request;
    }

    public function createSaleDiscountRequest(User $actor, array $data): ApprovalRequest
    {
        return DB::transaction(function () use ($actor, $data) {
            ApprovalRequest::query()
                ->where('requested_by', $actor->id)
                ->where('type', ApprovalRequest::TYPE_SALE_DISCOUNT)
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->update(['status' => ApprovalRequest::STATUS_CANCELLED]);

            return ApprovalRequest::create([
                'type' => ApprovalRequest::TYPE_SALE_DISCOUNT,
                'status' => ApprovalRequest::STATUS_PENDING,
                'requested_by' => $actor->id,
                'requested_discount_amount' => (float) $data['requested_discount_amount'],
                'discount_input_type' => $data['discount_input_type'],
                'discount_input_value' => (float) $data['discount_input_value'],
                'cart_subtotal' => (float) $data['cart_subtotal'],
                'payload' => $data['payload'],
            ]);
        });
    }

    public function approve(User $admin, ApprovalRequest $request, array $data): ApprovalRequest
    {
        $this->assertAdmin($admin);
        $this->assertPending($request);

        $approvedAmount = round((float) $data['approved_discount_amount'], 2);
        if ($approvedAmount <= 0) {
            throw ValidationException::withMessages([
                'approved_discount_amount' => ['Approved discount must be greater than zero.'],
            ]);
        }

        if ($approvedAmount > (float) $request->cart_subtotal) {
            throw ValidationException::withMessages([
                'approved_discount_amount' => ['Approved discount cannot exceed the cart subtotal.'],
            ]);
        }

        $request->fill([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'approved_discount_amount' => $approvedAmount,
            'approved_discount_input_type' => $data['approved_discount_input_type'] ?? $request->discount_input_type,
            'approved_discount_input_value' => (float) ($data['approved_discount_input_value'] ?? $approvedAmount),
            'rejection_reason' => null,
        ]);
        $request->save();

        return $request->fresh(['requester:id,name,email', 'reviewer:id,name,email']);
    }

    public function reject(User $admin, ApprovalRequest $request, ?string $reason = null): ApprovalRequest
    {
        $this->assertAdmin($admin);
        $this->assertPending($request);

        $request->fill([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);
        $request->save();

        return $request->fresh(['requester:id,name,email', 'reviewer:id,name,email']);
    }

    public function cancel(User $actor, ApprovalRequest $request): ApprovalRequest
    {
        if ((int) $request->requested_by !== (int) $actor->id) {
            abort(403, 'You are not allowed to cancel this approval request.');
        }

        $this->assertPending($request);

        $request->update(['status' => ApprovalRequest::STATUS_CANCELLED]);

        return $request->fresh(['requester:id,name,email', 'reviewer:id,name,email']);
    }

    public function consumeApprovedRequest(int $requestId, int $userId, float $saleDiscount, int $saleId): void
    {
        $request = ApprovalRequest::query()->lockForUpdate()->find($requestId);
        if (! $request) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['Discount approval request was not found.'],
            ]);
        }

        if ((int) $request->requested_by !== $userId) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['This discount approval request does not belong to you.'],
            ]);
        }

        if (! $request->isConsumable()) {
            throw ValidationException::withMessages([
                'discount_approval_request_id' => ['This discount approval request is not approved or has already been used.'],
            ]);
        }

        if (round((float) $request->approved_discount_amount, 2) !== round($saleDiscount, 2)) {
            throw ValidationException::withMessages([
                'discount' => ['Sale discount must match the approved discount amount.'],
            ]);
        }

        $request->update([
            'status' => ApprovalRequest::STATUS_CONSUMED,
            'consumed_at' => now(),
            'consumed_sale_id' => $saleId,
        ]);
    }

    public function serialize(ApprovalRequest $request): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type,
            'status' => $request->status,
            'requested_by' => $request->requested_by,
            'requester' => $request->relationLoaded('requester') && $request->requester
                ? [
                    'id' => $request->requester->id,
                    'name' => $request->requester->name,
                    'email' => $request->requester->email,
                ]
                : null,
            'reviewed_by' => $request->reviewed_by,
            'reviewer' => $request->relationLoaded('reviewer') && $request->reviewer
                ? [
                    'id' => $request->reviewer->id,
                    'name' => $request->reviewer->name,
                    'email' => $request->reviewer->email,
                ]
                : null,
            'reviewed_at' => $request->reviewed_at?->toIso8601String(),
            'requested_discount_amount' => (float) $request->requested_discount_amount,
            'approved_discount_amount' => $request->approved_discount_amount !== null
                ? (float) $request->approved_discount_amount
                : null,
            'discount_input_type' => $request->discount_input_type,
            'discount_input_value' => (float) $request->discount_input_value,
            'approved_discount_input_type' => $request->approved_discount_input_type,
            'approved_discount_input_value' => $request->approved_discount_input_value !== null
                ? (float) $request->approved_discount_input_value
                : null,
            'cart_subtotal' => (float) $request->cart_subtotal,
            'rejection_reason' => $request->rejection_reason,
            'payload' => $request->payload ?? [],
            'consumed_at' => $request->consumed_at?->toIso8601String(),
            'consumed_sale_id' => $request->consumed_sale_id,
            'created_at' => $request->created_at?->toIso8601String(),
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }

    private function assertAdmin(User $user): void
    {
        if ($user->role !== User::ROLE_ADMIN) {
            abort(403, 'Only administrators can perform this action.');
        }
    }

    private function assertPending(ApprovalRequest $request): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => ['Only pending approval requests can be updated.'],
            ]);
        }
    }
}
