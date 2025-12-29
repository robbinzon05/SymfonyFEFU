<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[OA\Tag(name: 'Users')]
final class UserController extends AbstractController
{
    #[OA\Post(
        path: '/users',
        summary: 'Create user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'password'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '+79990000002'),
                    new OA\Property(property: 'name', type: 'string', example: 'Ivan'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created'),
            new OA\Response(response: 400, description: 'Validation error'),
        ]
    )]
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
