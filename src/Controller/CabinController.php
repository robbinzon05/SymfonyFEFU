<?php

namespace App\Controller;

use App\services\ServicesCSV;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class CabinController extends AbstractController
{
    private ServicesCSV $csv;

    public function __construct()
    {
        $this->csv = new ServicesCSV(\dirname(__DIR__, 2));
    }

    #[Route('/cabins', name: 'cabins_list', methods: ['GET'])]
    public function list(Request $req): JsonResponse
    {
        $amenities = array_filter(array_map('trim', explode(',', (string)$req->query->get('amenities', ''))));
        $beds      = $req->query->getInt('beds', 0);
        $row       = $req->query->getInt('row', 0);

        $cabins = array_filter($this->csv->loadCabins(), function (array $c) use ($amenities, $beds, $row) {
            if ((int)$c['is_free'] !== 1) return false;

            if ($beds > 0 && (int)$c['beds'] < $beds) return false;
            if ($row  > 0 && (int)$c['row']  !== $row) return false;

            if ($amenities) {
                $has = array_filter(array_map('trim', explode(',', (string)$c['amenities'])));
                foreach ($amenities as $a) {
                    if (!in_array($a, $has, true)) return false;
                }
            }
            return true;
        });

        return $this->json(array_values($cabins));
    }

    #[Route('/bookings', name: 'booking_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];
        $phone = (string)($data['phone'] ?? '');
        $cabinId = (int)($data['cabin_id'] ?? 0);
        $comment = (string)($data['comment'] ?? '');

        if (!$phone || !$cabinId) {
            return $this->json(['error' => 'phone and cabin_id are required'], 400);
        }

        $cabins = $this->csv->loadCabins();
        $idx = null;
        foreach ($cabins as $i => $c) {
            if ((int)$c['id'] === $cabinId) { $idx = $i; break; }
        }
        if ($idx === null)  return $this->json(['error' => 'Cabin not found'], 404);
        if ((int)$cabins[$idx]['is_free'] !== 1) return $this->json(['error' => 'Cabin already booked'], 409);

        $bookings = $this->csv->loadBookings();
        $id = $this->csv->nextId($bookings);
        $bookings[] = [
            'id'         => $id,
            'phone'      => $phone,
            'cabin_id'   => $cabinId,
            'comment'    => $comment,
            'created_at' => (new \DateTimeImmutable())->format('c'),
        ];
        $this->csv->saveBookings($bookings);

        $cabins[$idx]['is_free'] = 0;
        $this->csv->saveCabins($cabins);

        return $this->json(['status' => 'ok', 'booking_id' => $id], 201);
    }

    #[Route('/bookings/{id<\d+>}', name: 'booking_update', methods: ['PUT'])]
    public function update(int $id, Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];
        if (!array_key_exists('comment', $data)) {
            return $this->json(['error' => 'comment is required'], 400);
        }
        $comment = (string)$data['comment'];

        $bookings = $this->csv->loadBookings();
        $found = false;
        foreach ($bookings as &$b) {
            if ((int)$b['id'] === $id) {
                $b['comment'] = $comment;
                $found = true;
                break;
            }
        }
        if (!$found) return $this->json(['error' => 'Booking not found'], 404);

        $this->csv->saveBookings($bookings);
        return $this->json(['status' => 'ok']);
    }
}
