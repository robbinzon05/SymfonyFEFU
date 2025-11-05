<?php

declare(strict_types=1);

namespace App\Controller;

use App\services\ServicesCSV;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class CabinController extends AbstractController
{
    private ServicesCSV $csv;

    public function __construct(ServicesCSV $csv)
    {
        $this->csv = $csv;
    }

    #[Route('/cabins', name: 'cabins_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $amenitiesCsv = (string) $request->query->get('amenities', '');
        $requiredAmenities = array_filter(array_map('trim', explode(',', $amenitiesCsv)));

        $minBeds = $request->query->getInt('beds', 0);
        $seasideRow = $request->query->getInt('row', 0);

        $cabins = $this->csv->loadCabins();
        $result = [];

        foreach ($cabins as $cabin) {
            if ((int) $cabin['is_free'] !== 1) {
                continue;
            }
            if ($minBeds > 0 && (int) $cabin['beds'] < $minBeds) {
                continue;
            }
            if ($seasideRow > 0 && (int) $cabin['row'] !== $seasideRow) {
                continue;
            }

            if ($requiredAmenities) {
                $cabinAmenities = array_filter(array_map('trim', explode(',', (string) $cabin['amenities'])));
                $ok = true;
                foreach ($requiredAmenities as $a) {
                    if (!in_array($a, $cabinAmenities, true)) {
                        $ok = false;
                        break;
                    }
                }
                if (!$ok) {
                    continue;
                }
            }

            $result[] = $cabin;
        }

        return $this->json(array_values($result));
    }

    #[Route('/bookings', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data    = json_decode($request->getContent(), true) ?? [];
        $phone   = (string) ($data['phone'] ?? '');
        $cabinId = (int) ($data['cabin_id'] ?? 0);
        $comment = (string) ($data['comment'] ?? '');

        if ($phone === '' || $cabinId === 0) {
            return $this->json(['error' => 'phone and cabin_id are required'], 400);
        }

        $cabins = $this->csv->loadCabins();
        $foundIndex = null;

        foreach ($cabins as $i => $cabin) {
            if ((int) $cabin['id'] === $cabinId) {
                $foundIndex = $i;
                break;
            }
        }

        if ($foundIndex === null) {
            return $this->json(['error' => 'Cabin not found'], 404);
        }
        if ((int) $cabins[$foundIndex]['is_free'] !== 1) {
            return $this->json(['error' => 'Cabin already booked'], 409);
        }

        $bookings = $this->csv->loadBookings();
        $newId = $this->csv->nextId($bookings);
        $bookings[] = [
            'id'         => $newId,
            'phone'      => $phone,
            'cabin_id'   => $cabinId,
            'comment'    => $comment,
            'created_at' => Carbon::now()->toIso8601String(),
        ];
        $this->csv->saveBookings($bookings);

        $cabins[$foundIndex]['is_free'] = 0;
        $this->csv->saveCabins($cabins);

        return $this->json(['status' => 'ok', 'booking_id' => $newId], 201);
    }

    #[Route('/bookings/{id<\d+>}', name: 'booking_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        if (!array_key_exists('comment', $data)) {
            return $this->json(['error' => 'comment is required'], 400);
        }
        $newComment = (string) $data['comment'];

        $bookings = $this->csv->loadBookings();
        $updated = false;

        foreach ($bookings as &$booking) {
            if ((int) $booking['id'] === $id) {
                $booking['comment'] = $newComment;
                $updated = true;
                break;
            }
        }
        unset($booking);

        if (!$updated) {
            return $this->json(['error' => 'Booking not found'], 404);
        }

        $this->csv->saveBookings($bookings);
        return $this->json(['status' => 'ok']);
    }
}
