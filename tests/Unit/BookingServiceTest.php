<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Booking;
use App\Entity\Cabin;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\CabinRepository;
use App\Service\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BookingServiceTest extends TestCase
{
    public function testCreateThrowsIfCabinNotFound(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $cabins = $this->createMock(CabinRepository::class);
        $bookings = $this->createMock(BookingRepository::class);

        $cabins->method('find')->willReturn(null);

        $service = new BookingService($em, $cabins, $bookings);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cabin not found');

        $service->create(new User(), 999, 'test comment');
    }

    public function testCreateSuccess(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $cabins = $this->createMock(CabinRepository::class);
        $bookings = $this->createMock(BookingRepository::class);

        $cabin = new Cabin();
        if (method_exists($cabin, 'setIsFree')) {
            $cabin->setIsFree(true);
        } else {
            $cabin->setIsFree(true);
        }

        $cabins->method('find')->willReturn($cabin);

        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Booking::class));
        $em->expects($this->once())->method('flush');

        $service = new BookingService($em, $cabins, $bookings);
        $user = new User();

        $booking = $service->create($user, 1, 'ok');
        $this->assertSame('ok', $booking->getComment());
        $this->assertSame($user, $booking->getOwner());
        $this->assertSame($cabin, $booking->getCabin());
    }
}
