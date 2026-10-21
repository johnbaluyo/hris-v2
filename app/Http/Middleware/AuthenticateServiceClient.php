<?php

namespace App\Http\Middleware;

use App\Models\ServiceClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateServiceClient
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = $request->bearerToken();
        $client = $token ? ServiceClient::where('token_hash', hash('sha256', $token))->whereNull('revoked_at')->first() : null;
        if (! $client || ! hash_equals($client->token_hash, hash('sha256', $token))) {
            return response()->json(['success' => false, 'message' => 'Unauthorized. Invalid or missing API token.'], 401);
        }
        abort_unless(in_array($scope, $client->scopes, true), 403, 'This service credential does not have the required scope.');
        $request->attributes->set('service_client', $client);

        return $next($request);
    }
}
