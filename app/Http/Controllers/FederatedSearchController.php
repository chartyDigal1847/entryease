<?php

namespace App\Http\Controllers;

use App\Http\Requests\FederatedSearchRequest;
use App\Services\Search\PortalFederatedSearchService;
use Illuminate\Http\JsonResponse;

class FederatedSearchController extends Controller
{
    public function __invoke(FederatedSearchRequest $request, PortalFederatedSearchService $search): JsonResponse
    {
        $validated = $request->validated();

        return response()->json(
            $search->search(
                (string) $validated['q'],
                (int) ($validated['limit'] ?? 8),
            ),
        );
    }
}
