<?php

namespace App\Http\Controllers;

use App\Actions\Notifications\SavePushSubscriptionAction;
use App\Http\Requests\Settings\DeletePushSubscriptionRequest;
use App\Http\Requests\Settings\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request, SavePushSubscriptionAction $saveSubscription): JsonResponse
    {
        $subscription = $saveSubscription($request->user(), $request->toDto());

        return response()->json([
            'id' => $subscription->id,
        ], Response::HTTP_CREATED);
    }

    public function destroy(DeletePushSubscriptionRequest $request): HttpResponse
    {
        $request->user()->deletePushSubscription($request->endpoint());

        return response()->noContent();
    }
}
