<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Cabin;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class BookingApiTest extends WebTestCase
{
    private function createFreeCabin(EntityManagerInterface $em): int
    {
        $cabin = new Cabin();
        $cabin->setBeds(2);
        $cabin->setRow(1);
        $cabin->setAmenities([]);
        $cabin->setIsFree(true);

        $em->persist($cabin);
        $em->flush();

        $id = $cabin->getId();
        if ($id === null) {
            throw new RuntimeException('Cabin ID was not generated');
        }

        return $id;
    }

    private function createUserAndGetToken(
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        string $phone,
        string $plainPassword
    ): string {
        $user = new User();
        $user
            ->setPhone($phone)
            ->setName('Tester')
            ->setCreatedAt(new DateTimeImmutable());

        $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));

        $em->persist($user);
        $em->flush();

        $client = static::createClient();
        $client->request(
            'POST',
            '/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'phone'    => $phone,
                'password' => $plainPassword,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data['token'])) {
            throw new RuntimeException('No token in login response');
        }

        return $data['token'];
    }

    public function testCreateBooking(): void
    {
        $client    = static::createClient();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $cabinId = $this->createFreeCabin($em);

        $token = $this->createUserAndGetToken(
            $em,
            $passwordHasher,
            '+79990000010',
            'secret-password',
        );

        $client->request(
            'POST',
            '/bookings',
            server: [
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_Authorization' => 'Bearer ' . $token,
            ],
            content: json_encode([
                'name'     => 'Bob',
                'phone'    => '+79990000002',
                'cabin_id' => $cabinId,
                'comment'  => 'test',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(201);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($cabinId, $json['cabin_id']);
        $this->assertSame('test', $json['comment']);
    }
}
