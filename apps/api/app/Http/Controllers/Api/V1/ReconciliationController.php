<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResolveReconciliationItemRequest;
use App\Http\Requests\StoreReconciliationRunRequest;
use App\Http\Resources\ReconciliationRunResource;
use App\Models\ProviderAccount;
use App\Models\ReconciliationItem;
use App\Models\ReconciliationRun;
use App\Services\Reconciliation\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReconciliationController extends Controller
{
    public function store(StoreReconciliationRunRequest $request, ReconciliationService $service): JsonResponse
    {
        Gate::authorize('create', ReconciliationRun::class);
        $account = ProviderAccount::query()->where('public_id', $request->validated('provider_account_public_id'))->firstOrFail();
        $run = $service->createRun($account, $request->user(), $request->validated());

        return (new ReconciliationRunResource($run))->response()->setStatusCode(201);
    }

    public function show(ReconciliationRun $run): ReconciliationRunResource
    {
        Gate::authorize('view', $run);

        return new ReconciliationRunResource($run);
    }

    public function resolve(ResolveReconciliationItemRequest $request, ReconciliationItem $item, ReconciliationService $service): JsonResponse
    {
        Gate::authorize('resolve', $item);
        $item = $service->resolve($item, $request->user(), $request->validated('resolution_note'));

        return response()->json(['public_id' => $item->public_id, 'resolution_status' => $item->resolution_status, 'resolved_at' => $item->resolved_at]);
    }
}
