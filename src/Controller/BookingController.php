<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/bookings')]
final class BookingController extends AbstractController
{
    public function __construct(
        private readonly BookingService $service,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'booking_create', methods: ['POST'])]
    public function create(Request $req): JsonResponse
    {
        $data = json_decode($req->getContent(), true) ?? [];

        $phone   = (string)($data['phone']    ?? '');
        $name    = (string)($data['name']     ?? '');
        $cabinId = (int)   ($data['cabin_id'] ?? 0);
        $comment = (string)($data['comment']  ?? '');

        if ($phone === '' || $cabinId === 0) {
            return $this->json(['error' => 'phone and cabin_id are required'], 400);
        }

        $user = $this->users->findOneBy(['phone' => $phone]);
        if (!$user) {
            $user = new User();
            $user->setPhone($phone);
            $user->setName($name !== '' ? $name : 'Guest');
            $this->em->persist($user);
            $this->em->flush();
        }

        try {
            $booking = $this->service->create($user, $cabinId, $comment);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], 409);
        }

        return $this->json([
            'id'        => $booking->getId(),
            'user_id'   => $booking->getOwner()->getId(),
            'cabin_id'  => $booking->getCabin()->getId(),
            'comment'   => $booking->getComment(),
            'created_at'=> $booking->getCreatedAt()->format('c'),
        ], 201);
    }
}
