<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserApiTest extends WebTestCase
{
    private EntityManagerInterface $em;

    #[Override]
    protected function setUp(): void
    {
        $client = static::createClient();
        $this->em = $client->getContainer()->get(EntityManagerInterface::class);
        $this->em->createQuery('DELETE FROM App\Entity\User u')->execute();
        $this->em->clear();
    }

    #[Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->em->close();
        unset($this->em);
    }

    public function testCreateNewUserWithPassword(): void
    {
        $client = static::createClient();

        $payload = [
            'phone'    => '+79990000001',
            'name'     => 'Test User',
            'password' => 'secret123',
        ];

        $client->request(
            'POST',
            '/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);

        $responseData = json_decode(
            (string) $client->getResponse()->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        self::assertArrayHasKey('id', $responseData);
        self::assertSame($payload['phone'], $responseData['phone']);
        self::assertSame($payload['name'], $responseData['name']);
        self::assertContains('ROLE_USER', $responseData['roles']);

        /** @var User|null $user */
        $user = $this->em->getRepository(User::class)->findOneBy(['phone' => $payload['phone']]);

        self::assertInstanceOf(User::class, $user);
        self::assertNotNull($user->getPassword(), 'Password must be hashed and stored');
    }

    public function testExistingUserWithoutPasswordGetsPasswordSet(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setPhone('+79990000002');
        $user->setName('User2');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new DateTimeImmutable());

        $this->em->persist($user);
        $this->em->flush();
        $this->em->clear();

        $client = static::createClient();

        $payload = [
            'phone'    => '+79990000002',
            'password' => '123',
        ];

        $client->request(
            'POST',
            '/users',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($payload, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);

        /** @var User|null $updated */
        $updated = $this->em->getRepository(User::class)->findOneBy(['phone' => $payload['phone']]);

        self::assertInstanceOf(User::class, $updated);
        self::assertNotNull($updated->getPassword(), 'Password must be set for existing user');

        self::assertTrue(
            $passwordHasher->isPasswordValid($updated, $payload['password']),
            'Stored password hash must match provided password'
        );

        $all = $this->em->getRepository(User::class)->findBy(['phone' => $payload['phone']]);
        self::assertCount(1, $all);
    }
}
