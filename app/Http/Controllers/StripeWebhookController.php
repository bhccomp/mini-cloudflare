<?php

namespace App\Http\Controllers;

use App\Services\Billing\StripeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeWebhookService $service): Response
    {
        try {
            $service->handle(
                $request->getContent(),
                (string) $request->header('Stripe-Signature', ''),
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            return response($exception->getMessage(), 400);
        }

        return response('Webhook handled.', 200);
    }
}
