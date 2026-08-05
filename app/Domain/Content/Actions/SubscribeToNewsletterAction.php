<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;

final class SubscribeToNewsletterAction
{
    /**
     * Subscribes an email, or re-subscribes one that had opted out.
     *
     * Idempotent on purpose: someone pressing Subscribe twice should not see an error
     * about an email already existing. It tells them nothing useful and reads as a bug.
     *
     * @return bool true if this was a new or reactivated subscription
     */
    public function execute(string $email, ?string $ip): bool
    {
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $wasSubscribed = $subscriber->exists && $subscriber->unsubscribed_at === null;

        $subscriber->fill([
            'ip_address' => $ip,
            'subscribed_at' => $subscriber->subscribed_at ?? now(),
            'unsubscribed_at' => null,
        ])->save();

        Log::info('content.subscribe_to_newsletter.success', [
            'email' => $email,
            'already_subscribed' => $wasSubscribed,
        ]);

        return ! $wasSubscribed;
    }
}
