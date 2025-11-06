<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class CabinController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    #[Route('/cabins', name: 'cabins_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $amenitiesCsv = (string) $request->query->get('amenities', '');
        $requiredAmenities = array_values(array_filter(array_map('trim', explode(',', $amenitiesCsv))));
        $minBeds = $request->query->getInt('beds', 0);
        $row     = $request->query->getInt('row', 0);

        $cabins = $this->bookingService->listFreeCabins($requiredAmenities, $minBeds, $row);

        return $this->json(array_map(static fn($c) => [
            'id'        => $c->getId(),
            'beds'      => $c->getBeds(),
            'row'       => $c->getRow(),
            'amenities' => $c->getAmenities(),
            'is_free'   => $c->isFree(),
        ], $cabins));
    }

    #[Route('/bookings', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $phone   = (string)($data['phone'] ?? '');
        $cabinId = (int)($data['cabin_id'] ?? 0);
        $comment = (string)($data['comment'] ?? '');

        if ($phone === '' || $cabinId === 0) {
            return $this->json(['error' => 'phone and cabin_id are required'], 400);
        }

        try {
            $booking = $this->bookingService->createBooking($phone, $cabinId, $comment);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json(['status' => 'ok', 'booking_id' => $booking->getId()], 201);
    }

    #[Route('/bookings/{id<\d+>}', name: 'booking_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('comment', $data)) {
            return $this->json(['error' => 'comment is required'], 400);
        }

        $ok = $this->bookingService->updateBookingComment($id, (string)$data['comment']);
        if (!$ok) {
            return $this->json(['error' => 'Booking not found'], 404);
        }

        return $this->json(['status' => 'ok']);
    }
}
