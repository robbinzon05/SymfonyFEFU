<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserApiTest extends WebTestCase
{
    public function testCreateUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'name'  => 'John',
            'phone' => '+79990000001',
        ], JSON_THROW_ON_ERROR));

        $this->assertResponseStatusCodeSame(201);
        $json = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('id', $json);
        $this->assertSame('John', $json['name']);
        $this->assertSame('+79990000001', $json['phone']);
    }
}
