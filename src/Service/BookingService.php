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
use DomainException;
use InvalidArgumentException;

final class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CabinRepository $cabins,
        private readonly BookingRepository $bookings,
    ) {
    }

    /**
     * @return Cabin[]
     */
    public function listFreeCabins(array $requiredAmenities, int $minBeds, int $row): array
    {
        $qb = $this->cabins->createQueryBuilder('c')
            ->andWhere('c.isFree = :free')
            ->setParameter('free', true);

        if ($minBeds > 0) {
            $qb->andWhere('c.beds >= :beds')->setParameter('beds', $minBeds);
        }

        if ($row > 0) {
            $qb->andWhere('c.row = :row')->setParameter('row', $row);
        }

        $cabins = $qb->getQuery()->getResult();

        if (!$requiredAmenities) {
            return $cabins;
        }

        return array_values(array_filter(
            $cabins,
            fn (Cabin $c) => empty(array_diff($requiredAmenities, $c->getAmenities()))
        ));
    }

    public function create(User $user, int $cabinId, string $comment): Booking
    {
        $cabin = $this->cabins->find($cabinId);

        if (!$cabin) {
            throw new InvalidArgumentException('Cabin not found');
        }

        if (!$cabin->isFree()) {
            throw new DomainException('Cabin already booked');
        }

        $booking = (new Booking())
            ->setOwner($user)
            ->setCabin($cabin)
            ->setComment($comment)
            ->setCreatedAt(new DateTimeImmutable());

        $cabin->setIsFree(false);

        $this->em->persist($booking);
        $this->em->flush();

        return $booking;
    }

    public function updateComment(int $bookingId, string $comment): bool
    {
        $booking = $this->bookings->find($bookingId);

        if (!$booking) {
            return false;
        }

        $booking->setComment($comment);
        $this->em->flush();

        return true;
    }
}
