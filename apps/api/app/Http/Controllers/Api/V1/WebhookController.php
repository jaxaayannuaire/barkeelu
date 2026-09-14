<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccount;
use App\Services\Webhooks\WebhookIngressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function store(Request $request, string $provider, WebhookIngressService $service): JsonResponse
    {
        $account = ProviderAccount::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->firstOrFail();

        // Aucun secret fournisseur n’est disponible dans 06B : échec fermé.
        $event = $service->receive($account, $request->getContent(), $request->headers->all(), false);

        return response()->json(['status' => 'accepted', 'event_id' => $event->public_id], 202);
    }
}
