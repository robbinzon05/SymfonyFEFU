<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\BookingService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Cabins')]
#[Route('/cabins')]
final class CabinController extends AbstractController
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {
    }

    #[OA\Get(
        path: '/cabins',
        summary: 'List free cabins by filters',
        description: 'Returns a list of free cabins filtered by amenities, minimal beds count and row.',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'amenities',
                in: 'query',
                required: false,
                description: 'Comma-separated amenities (e.g. wifi,parking)',
                schema: new OA\Schema(type: 'string', example: 'wifi,parking')
            ),
            new OA\Parameter(
                name: 'beds',
                in: 'query',
                required: false,
                description: 'Minimum number of beds',
                schema: new OA\Schema(type: 'integer', minimum: 0, example: 2)
            ),
            new OA\Parameter(
                name: 'row',
                in: 'query',
                required: false,
                description: 'Cabin row number',
                schema: new OA\Schema(type: 'integer', minimum: 0, example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of cabins',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'integer', example: 1),
                            new OA\Property(property: 'beds', type: 'integer', example: 4),
                            new OA\Property(property: 'row', type: 'integer', example: 2),
                            new OA\Property(
                                property: 'amenities',
                                type: 'array',
                                items: new OA\Items(type: 'string'),
                                example: ['wifi', 'parking']
                            ),
                            new OA\Property(property: 'is_free', type: 'boolean', example: true),
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
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
