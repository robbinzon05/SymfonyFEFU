<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em): JsonResponse
    {
        try {
            $data = $request->toArray();
        } catch (Throwable) {
            $data = $request->request->all();
        }

        $name  = trim((string)($data['name']  ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));

        if ($name === '' || $phone === '') {
            throw new HttpException(400, 'phone and cabin_id are required');
        }

        $user = (new User())
            ->setName($name)
            ->setPhone($phone)
            ->setCreatedAt(new DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return $this->json([
            'id'    => $user->getId(),
            'name'  => $user->getName(),
            'phone' => $user->getPhone(),
        ], 201);
    }
}
