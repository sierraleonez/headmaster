<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * The well-known document browsers read to offer passkey management.
 *
 * A controller rather than a closure so the route table can be cached.
 */
class PasskeyEndpointsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'enroll' => route('security.edit'),
            'manage' => route('security.edit'),
        ]);
    }
}
