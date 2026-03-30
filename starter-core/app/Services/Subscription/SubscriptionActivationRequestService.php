<?php

namespace App\Services\Subscription;

use App\Models\SubscriptionActivationRequest;

final class SubscriptionActivationRequestService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubscriptionActivationRequest
    {
        return SubscriptionActivationRequest::query()->create($data);
    }
}
