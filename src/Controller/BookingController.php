<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Bookings')]
#[Route('/bookings')]
final class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingService $service,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[OA\Post(
        path: '/bookings',
        summary: 'Create booking',
        description: 'Creates a booking for a cabin. If user with provided phone does not exist, it will be created.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'cabin_id'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '+79990000001'),
                    new OA\Property(property: 'name', type: 'string', example: 'Guest'),
                    new OA\Property(property: 'cabin_id', type: 'integer', example: 1),
                    new OA\Property(property: 'comment', type: 'string', example: 'Please prepare towels'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Booking created',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 10),
                        new OA\Property(property: 'user_id', type: 'integer', example: 3),
                        new OA\Property(property: 'cabin_id', type: 'integer', example: 1),
                        new OA\Property(property: 'comment', type: 'string', example: 'Please prepare towels'),
                        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-12-03T14:35:04+00:00'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'phone and cabin_id are required'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 409, description: 'Cabin not free or not found'),
        ]
    )]
    #[Route('', name: 'booking_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];

        $phone   = (string) ($payload['phone']    ?? '');
        $name    = (string) ($payload['name']     ?? '');
        $cabinId = (int)    ($payload['cabin_id'] ?? 0);
        $comment = (string) ($payload['comment']  ?? '');

        if ($phone === '' || $cabinId <= 0) {
            throw new HttpException(400, 'phone and cabin_id are required');
        }

        $user = $this->users->findOneBy(['phone' => $phone]);

        if ($user === null) {
            $user = new User();
            $user->setPhone($phone);
            $user->setName($name !== '' ? $name : 'Guest');

            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        try {
            $booking = $this->service->create($user, $cabinId, $comment);
        } catch (RuntimeException $e) {
            throw new HttpException(409, $e->getMessage(), $e);
        }

        return $this->json(
            [
                'id'         => $booking->getId(),
                'user_id'    => $booking->getOwner()->getId(),
                'cabin_id'   => $booking->getCabin()->getId(),
                'comment'    => $booking->getComment(),
                'created_at' => $booking->getCreatedAt()->format('c'),
            ],
            201,
        );
    }
}
