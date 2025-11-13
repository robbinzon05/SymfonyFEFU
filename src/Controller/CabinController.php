<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/cabins')]
final class CabinController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    #[Route('', name: 'cabins_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $amenitiesCsv = (string) $request->query->get('amenities', '');
        $requiredAmenities = array_values(array_filter(array_map('trim', explode(',', $amenitiesCsv))));
        
        $minBeds = $request->query->getInt('beds', 0);
        $row     = $request->query->getInt('row', 0);

        $cabins = $this->bookingService->listFreeCabins(
            $requiredAmenities,
            $minBeds,
            $row
        );

        return $this->json(array_map(static fn ($cabin) => [
            'id'        => $cabin->getId(),
            'beds'      => $cabin->getBeds(),
            'row'       => $cabin->getRow(),
            'amenities' => $cabin->getAmenities(),
            'is_free'   => $cabin->isFree(),
        ], $cabins));
    }
}
