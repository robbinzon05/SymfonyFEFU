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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'user_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        try {
            $data = $request->toArray();
        } catch (Throwable) {
            $data = $request->request->all();
        }

        $phone = trim((string) ($data['phone'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        $plainPassword = (string) ($data['password'] ?? '');

        if ($phone === '' || $plainPassword === '') {
            throw new HttpException(400, 'phone and password are required');
        }

        /** @var \App\Repository\UserRepository $repo */
        $repo = $em->getRepository(User::class);

        /** @var User|null $existing */
        $existing = $repo->findOneBy(['phone' => $phone]);

        if ($existing instanceof User) {
            if ($existing->getPassword() === null) {
                $hashed = $passwordHasher->hashPassword($existing, $plainPassword);
                $existing->setPassword($hashed);
                $em->flush();
            }

            return $this->json([
                'id'    => $existing->getId(),
                'phone' => $existing->getPhone(),
                'name'  => $existing->getName(),
                'roles' => $existing->getRoles(),
            ]);
        }

        $user = new User();
        $user->setPhone($phone);
        $user->setName($name !== '' ? $name : null);
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new DateTimeImmutable());

        $hashed = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashed);

        $em->persist($user);
        $em->flush();

        return $this->json([
            'id'    => $user->getId(),
            'phone' => $user->getPhone(),
            'name'  => $user->getName(),
            'roles' => $user->getRoles(),
        ], 201);
    }
}
