<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each throttled route must count its own requests, not everybody else's.
 *
 * Laravel's inline `throttle:N,M` builds its cache key from the domain and the IP alone —
 * ThrottleRequests::resolveRequestSignature(), and the limit number is deliberately NOT
 * part of it. Every route declared as `throttle:N,1` with no third argument therefore
 * shared ONE counter per visitor, and each middleware merely compared that shared count
 * against its own ceiling.
 *
 * What that meant here: admin login allows 6 a minute, the coupon field allows 30. Six
 * requests to the coupon field burned the login's entire budget, so the shop owner was
 * locked out of their own panel by ordinary shop traffic, before a password was tried.
 * On a laptop nobody notices. On a public host it is a self-inflicted denial of service,
 * and anyone who wants to lock you out only has to load a page seven times.
 *
 * The fix is the third argument — a name per counter. This test is the only thing that
 * distinguishes "named" from "looks named", since a typo in a prefix fails silently by
 * simply sharing again.
 */
final class RateLimitsAreNotSharedTest extends TestCase
{
    use RefreshDatabase;

    /** Comfortably past admin-login's 6, comfortably inside coupon-check's 30. */
    private const BURST = 12;

    public function test_using_the_coupon_field_does_not_lock_the_owner_out_of_the_admin(): void
    {
        for ($i = 0; $i < self::BURST; $i++) {
            $this->get('/cart/coupon/check?code=NOPE'.$i)->assertOk();
        }

        // Wrong credentials on purpose — the assertion is about the gate, not the password.
        // Before the fix this was 429: the login's budget of 6 was already spent above.
        $response = $this->post('/admin/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertNotSame(429, $response->getStatusCode(), 'Shop traffic exhausted the admin login limit.');
    }

    public function test_the_coupon_check_still_has_a_limit_of_its_own(): void
    {
        // Naming the counters must not have quietly removed the protection. The apply
        // endpoint is the one that guards the code space, so prove the check refuses too.
        $refused = false;

        for ($i = 0; $i < 40; $i++) {
            if ($this->get('/cart/coupon/check?code=NOPE'.$i)->getStatusCode() === 429) {
                $refused = true;
                break;
            }
        }

        $this->assertTrue($refused, 'The coupon check accepted 40 requests in a minute — its throttle is not firing.');
    }

    public function test_the_newsletter_and_the_coupon_field_do_not_share_a_budget(): void
    {
        // The newsletter allows 10. Burn 12 on the coupon field and it must still work.
        for ($i = 0; $i < self::BURST; $i++) {
            $this->get('/cart/coupon/check?code=NOPE'.$i);
        }

        $response = $this->post('/newsletter', ['email' => 'someone@example.com']);

        $this->assertNotSame(429, $response->getStatusCode(), 'The coupon field spent the newsletter budget.');
    }
}
