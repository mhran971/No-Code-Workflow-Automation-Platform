<?php

namespace Modules\Integrations\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Integrations\Models\IntegrationProvider;
use Modules\Integrations\Models\IntegrationConnection;
use Illuminate\Support\Facades\Crypt;

class IntegrationsController extends Controller
{
    public function clickupConnect($id)
    {
        $state = base64_encode($id . ':' . bin2hex(random_bytes(16)));
        
        $authUrl = 'https://app.clickup.com/api?' . http_build_query([
            'client_id' => env("CLICKUP_CLIENT_ID"),
            'redirect_uri' => route('api.integrations.clickup.callback'),
            'state' => $state,
        ]);
        
        return redirect($authUrl);
    }

     public function clickupCallback(Request $request)
    {
        // Validate state
        [$tenantId, $nonce] = explode(':', base64_decode($request->state));
        
        // Exchange code
        $response = Http::post('https://api.clickup.com/api/v2/oauth/token', [
            'client_id' => env("CLICKUP_CLIENT_ID"),
            'client_secret' => env("CLICKUP_CLIENT_SECRET"),
            'code' => $request->code,
            ]);

        $provider = IntegrationProvider::where('id', 'clickup')->firstOrFail();
        
        IntegrationConnection::create([
            'integration_provider_id' => $provider->id,
            'tenant_id' => $tenantId,
            'auth_config' => ['access_token' => Crypt::encrypt($response['access_token'])],
            'config' => ['teams' => $this->getClickupTeams($response['access_token'])],
            ]);
            
        return "sucess";
        return redirect("/integrations?success=clickup");
    }

    private function getClickupTeams($token)
    {
        $response = Http::withToken($token)->get('https://api.clickup.com/api/v2/team');

        return $response->json('teams') ?? [];
    }
}
