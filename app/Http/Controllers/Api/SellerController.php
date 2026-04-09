<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SellerRequest;
use App\Models\Seller;
use Illuminate\Http\JsonResponse;

class SellerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Seller::paginate(20));
    }

    public function store(SellerRequest $request): JsonResponse
    {
        $seller = Seller::create($request->validated());

        return response()->json($seller, 201);
    }

    public function show(Seller $seller): JsonResponse
    {
        return response()->json($seller);
    }

    public function update(SellerRequest $request, Seller $seller): JsonResponse
    {
        $seller->update($request->validated());

        return response()->json($seller);
    }

    public function destroy(Seller $seller): JsonResponse
    {
        $seller->delete();

        return response()->json([], 204);
    }
}
