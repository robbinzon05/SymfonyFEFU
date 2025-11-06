<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = $request->request->all();
        }

        $name  = trim((string)($data['name']  ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));

        if ($name === '' || $phone === '') {
            return $this->json(['error' => 'name and phone are required'], 400);
        }

        $user = (new User())
            ->setName($name)
            ->setPhone($phone)
            ->setCreatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->json([
            'id'    => $user->getId(),
            'name'  => $user->getName(),
            'phone' => $user->getPhone(),
        ], 201);
    }
}
