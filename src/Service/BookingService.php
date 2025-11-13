<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Cabin;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\CabinRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CabinRepository $cabins,
        private readonly BookingRepository $bookings,
    ) {
    }

    public function create(User $user, int $cabinId, ?string $comment = null): Booking
    {
        /** @var Cabin|null $cabin */
        $cabin = $this->cabins->find($cabinId);
        if (!$cabin) {
            throw new RuntimeException('Cabin not found');
        }

        if (method_exists($cabin, 'isFree') ? !$cabin->isFree() : (int)$cabin->getIsFree() !== 1) {
            throw new RuntimeException('Cabin already booked');
        }

        $booking = new Booking();
        $booking->setOwner($user);
        $booking->setCabin($cabin);
        $booking->setComment($comment ?? '');
        $booking->setCreatedAt(new DateTimeImmutable());

        if (method_exists($cabin, 'setIsFree')) {
            $cabin->setIsFree(false);
        } else {
            $cabin->setIsFree(0);
        }

        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }
}
