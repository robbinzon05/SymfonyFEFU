<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Throwable;

#[OA\Tag(name: 'Auth')]
final class AuthController extends AbstractController
{
    #[OA\Post(
        path: '/login',
        summary: 'Login and get access token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['phone', 'password'],
                properties: [
                    new OA\Property(property: 'phone', type: 'string', example: '+79990000001'),
                    new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token issued',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'phone and password are required'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    #[Route('/login', name: 'auth_login', methods: ['POST'])]
    public function login(
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
        $password = (string) ($data['password'] ?? '');

        if ($phone === '' || $password === '') {
            throw new HttpException(400, 'phone and password are required');
        }

        /** @var User|null $user */
        $user = $em->getRepository(User::class)->findOneBy(['phone' => $phone]);

        if (!$user instanceof User) {
            throw new HttpException(401, 'Invalid credentials');
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            throw new HttpException(401, 'Invalid credentials');
        }

        $token = bin2hex(random_bytes(32));
        $user->setApiToken($token);
        $em->flush();

        return $this->json([
            'token' => $token,
            'user'  => [
                'id'    => $user->getId(),
                'phone' => $user->getPhone(),
                'name'  => $user->getName(),
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/logout',
        summary: 'Logout (invalidate token)',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out'),
            new OA\Response(response: 401, description: 'Not authenticated'),
        ]
    )]
    #[Route('/logout', name: 'auth_logout', methods: ['POST'])]
    public function logout(
        #[CurrentUser] ?User $user,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (!$user instanceof User) {
            throw new HttpException(401, 'Not authenticated');
        }

        $user->setApiToken(null);
        $em->flush();

        return $this->json(['status' => 'logged_out']);
    }
}
