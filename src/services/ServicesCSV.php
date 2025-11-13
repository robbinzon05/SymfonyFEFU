<?php

namespace App\services;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ServicesCSV
{
    private string $cabinsFile;
    private string $bookingsFile;

    public function __construct(string $rootDir)
    {
        $this->cabinsFile = $cabinsFile;
        $this->bookingsFile = $bookingsFile;
    }

    /** @return array<int,array<string,mixed>> */
    public function loadCabins(): array
    {
        return $this->readCsv($this->cabinsFile);
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function saveCabins(array $rows): void
    {
        $this->writeCsv($this->cabinsFile, $rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function loadBookings(): array
    {
        return $this->readCsv($this->bookingsFile);
    }

    /** @param array<int,array<string,mixed>> $rows */
    public function saveBookings(array $rows): void
    {
        $this->writeCsv($this->bookingsFile, $rows);
    }

    /** @return array<int,array<string,mixed>> */
    private function readCsv(string $path): array
    {
        if (!is_file($path)) {
            throw new NotFoundHttpException("CSV not found: $path");
        }

        $f = fopen($path, 'r');
        if (!$f) {
            throw new \RuntimeException("Cannot open: $path");
        }
        flock($f, LOCK_SH);

        $header = null;
        $rows = [];
        while (($line = fgetcsv($f, 0, ';')) !== false) {
            if ($header === null) {
                $header = $line;
                continue;
            }
            if ($line === [null] || $line === false) {
                continue;
            }
            $rows[] = $this->combine($header, $line);
        }

        flock($f, LOCK_UN);
        fclose($f);
        return $rows;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function writeCsv(string $path, array $rows): void
    {
        if (!is_file($path)) {
            throw new NotFoundHttpException("CSV not found: $path");
        }
        $f = fopen($path, 'c+');
        if (!$f) {
            throw new \RuntimeException("Cannot open: $path");
        }
        if (!flock($f, LOCK_EX)) {
            fclose($f);
            throw new \RuntimeException("Cannot lock: $path");
        }

        rewind($f);
        $header = fgetcsv($f, 0, ';');
        if ($header === false || $header === [null]) {
            throw new \RuntimeException("CSV header missing in $path");
        }

        ftruncate($f, 0);
        rewind($f);
        fputcsv($f, $header, ';');

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($f, $line, ';');
        }

        fflush($f);
        flock($f, LOCK_UN);
        fclose($f);
    }

    /** @param array<int,string> $header @param array<int,string> $line */
    private function combine(array $header, array $line): array
    {
        $assoc = [];
        foreach ($header as $i => $col) {
            $assoc[$col] = $line[$i] ?? null;
        }
        if (isset($assoc['id']))      $assoc['id'] = (int)$assoc['id'];
        if (isset($assoc['beds']))    $assoc['beds'] = (int)$assoc['beds'];
        if (isset($assoc['row']))     $assoc['row'] = (int)$assoc['row'];
        if (isset($assoc['is_free'])) $assoc['is_free'] = (int)$assoc['is_free'];
        return $assoc;
    }

    public function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $r) $max = max($max, (int)($r['id'] ?? 0));
        return $max + 1;
    }
}
