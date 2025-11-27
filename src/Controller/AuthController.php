<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Throwable;

final class AuthController extends AbstractController
{
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
