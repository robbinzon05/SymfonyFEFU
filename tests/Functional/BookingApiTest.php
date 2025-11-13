<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Cabin;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

    public function testCreateBookingCreatesUserIfNotExistsAndMarksCabinBusy(): void
    {
        $client = static::createClient();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $cabinId = $this->createFreeCabin($em);

        $client->request('POST', '/bookings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name'     => 'Bob',
            'phone'    => '+79990000002',
            'cabin_id' => $cabinId,
            'comment'  => 'test',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('id', $json);
        $this->assertSame($cabinId, $json['cabin_id']);
        $this->assertSame('test', $json['comment']);
    }
}
