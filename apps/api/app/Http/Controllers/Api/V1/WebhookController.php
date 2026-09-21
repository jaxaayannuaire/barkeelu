<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccount;
use App\Services\Payments\ProviderGatewayResolver;
use App\Services\Webhooks\WebhookIngressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function store(Request $request, string $provider, WebhookIngressService $service, ProviderGatewayResolver $gateways): JsonResponse
    {
        $canonicalProvider = strtoupper($provider);
        if ($canonicalProvider !== 'WAVE') {
            abort(404);
        }

        $accounts = ProviderAccount::query()
            ->whereRaw('UPPER(provider) = ?', [$canonicalProvider])
            ->where('is_active', true)
            ->get();
        if ($accounts->count() !== 1) {
            abort(404);
        }
        $account = $accounts->sole();

        // Aucun secret fournisseur n’est disponible dans 06B : échec fermé.
        $valid = $gateways->for($account)->verifyWebhook($request->getContent(), $request->headers->all());
        $event = $service->receive($account, $request->getContent(), $request->headers->all(), $valid);

        if (! $valid) {
            return response()->json(['status' => 'rejected'], 401);
        }

        return response()->json(['status' => 'accepted', 'event_id' => $event->public_id], 202);
    }
}
