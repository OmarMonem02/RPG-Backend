<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $globalSearchService,
    ) {}

    public function __invoke(SearchRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return $this->successResponse('Search completed successfully.', $this->globalSearchService->execute(
            $validated['q'],
            $validated['limit'] ?? 10,
        ));
    }
}
