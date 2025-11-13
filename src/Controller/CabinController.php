<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

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

        return $this->json(array_map(static fn($cabin) => [
            'id'        => $cabin->getId(),
            'beds'      => $cabin->getBeds(),
            'row'       => $cabin->getRow(),
            'amenities' => $cabin->getAmenities(),
            'is_free'   => $cabin->isFree(),
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
            throw new HttpException(400, 'phone and cabin_id are required');
        }

        try {
            $booking = $this->bookingService->createBooking($phone, $cabinId, $comment);
        } catch (\InvalidArgumentException $e) {
            throw new HttpException(404, $e->getMessage(), $e);
        } catch (\DomainException $e) {
            throw new HttpException(409, $e->getMessage(), $e);
        }

        return $this->json(['status' => 'ok', 'booking_id' => $booking->getId()], 201);
    }

    #[Route('/bookings/{id<\d+>}', name: 'booking_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('comment', $data)) {
            throw new HttpException(400, 'comment is required');
        }

        $ok = $this->bookingService->updateBookingComment($id, (string)$data['comment']);
        if (!$ok) {
            throw new HttpException(404, $e->getMessage(), $e);
        }

        return $this->json(['status' => 'ok']);
    }
}
