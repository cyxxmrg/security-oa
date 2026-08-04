<?php

namespace Tests\Unit\Auth;

use App\Services\PortalInviteService;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PortalAccessTokenTest extends TestCase
{
    public function test_it_prefers_bearer_tokens_and_keeps_body_fallback_for_old_clients(): void
    {
        $service = new class extends PortalInviteService {
            public function token(Request $request): string
            {
                return $this->tokenFromRequest($request);
            }
        };

        $bearerRequest = Request::create('/portal/invitations', 'GET', ['access_token' => 'legacy-token']);
        $bearerRequest->headers->set('Authorization', 'Bearer current-token');
        $this->assertSame('current-token', $service->token($bearerRequest));

        $legacyRequest = Request::create('/portal/invitations', 'POST', ['access_token' => 'legacy-token']);
        $this->assertSame('legacy-token', $service->token($legacyRequest));
    }
}
