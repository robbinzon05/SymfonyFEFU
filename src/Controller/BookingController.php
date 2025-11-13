<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bookings')]
final class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingService $service,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

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
        } catch (\RuntimeException $e) {
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
