<?php

namespace App\Actions\Notifications;

use App\Data\PushSubscriptionData;
use App\Models\User;
use NotificationChannels\WebPush\PushSubscription;

class SavePushSubscriptionAction
{
    public function __invoke(User $user, PushSubscriptionData $subscription): PushSubscription
    {
        return $user->updatePushSubscription(
            endpoint: $subscription->endpoint,
            key: $subscription->publicKey,
            token: $subscription->authToken,
            contentEncoding: $subscription->contentEncoding,
        );
    }
}
