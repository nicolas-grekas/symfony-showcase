<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stands in for the API of the travel agency in development.
 */
final class FakeTravelAgencyController
{
    #[Route('/_fake/travel-agency/bookings', methods: ['POST'], env: 'dev')]
    public function book(Request $request): JsonResponse
    {
        $booking = $request->toArray();

        return new JsonResponse(['reference' => 'TRV-'.strtoupper(substr(md5(json_encode($booking)), 0, 8))], 201);
    }
}
