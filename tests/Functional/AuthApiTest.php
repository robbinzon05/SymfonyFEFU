<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AuthApiTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        static::ensureKernelShutdown();
        static::createClient();
        $container = static::getContainer();

        $this->em = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
    }

    private function createUser(string $phone, string $password): User
    {
        $user = new User();
        $user
            ->setPhone($phone)
            ->setName('Test User')
            ->setCreatedAt(new DateTimeImmutable());

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @return array{client: \Symfony\Bundle\FrameworkBundle\KernelBrowser, response: Response}
     */
    private function login(string $phone, string $password): array
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'phone'    => $phone,
                'password' => $password,
            ], JSON_THROW_ON_ERROR),
        );

        return [
            'client'   => $client,
            'response' => $client->getResponse(),
        ];
    }

    public function testLoginSuccessAndUseToken(): void
    {
        $phone = '+79990000050';
        $password = 'top-secret';

        $this->createUser($phone, $password);

        $result = $this->login($phone, $password);
        /** @var Response $response */
        $response = $result['response'];

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $data);
        self::assertIsString($data['token']);
        self::assertNotSame('', $data['token']);

        $token = $data['token'];

        /** @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client = $result['client'];

        $client->request(
            'GET',
            '/cabins',
            server: [
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testLoginInvalidPassword(): void
    {
        $phone    = '+79990000051';
        $password = 'correct-password';

        $this->createUser($phone, $password);

        $result = $this->login($phone, 'wrong-password');
        /** @var Response $response */
        $response = $result['response'];

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testProtectedEndpointWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/cabins');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testLogoutInvalidatesToken(): void
    {
        $phone    = '+79990000052';
        $password = 'logout-test';

        $this->createUser($phone, $password);

        $result = $this->login($phone, $password);
        /** @var Response $response */
        $response = $result['response'];

        $data  = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $data['token'];

        /** @var \Symfony\Bundle\FrameworkBundle\KernelBrowser $client */
        $client = $result['client'];

        $client->request(
            'POST',
            '/logout',
            server: [
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
        );

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request(
            'GET',
            '/cabins',
            server: [
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
}
