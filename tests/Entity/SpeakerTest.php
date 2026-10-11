<?php

namespace App\Tests\Entity;

use App\Entity\Speaker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SpeakerTest extends KernelTestCase
{
    public function testThePhoneNumberIsEncryptedAtRest()
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $stored = $entityManager->getConnection()->fetchOne('SELECT phone FROM speaker WHERE email = ?', ['alice@example.com']);
        $stored = \is_resource($stored) ? stream_get_contents($stored) : $stored;
        $this->assertNotEmpty($stored);
        $this->assertStringNotContainsString('12 34 56 78', $stored);

        $speaker = $entityManager->getRepository(Speaker::class)->findOneBy(['email' => 'alice@example.com']);
        $this->assertSame('+33 6 12 34 56 78', $speaker->phone);
    }

    public function testTheDataKeyIsSharedByTheColumn()
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $speaker = new Speaker('dave@example.com');
        $speaker->name = 'Dave';
        $speaker->bio = 'Dave talks about encryption.';
        $speaker->phone = '+49 30 1234567';
        $entityManager->persist($speaker);
        $entityManager->flush();

        // the payloads refer to the data key of their scope, kept wrapped in a table
        $this->assertSame(1, (int) $entityManager->getConnection()->fetchOne("SELECT COUNT(*) FROM data_keys WHERE scope = 'speaker.contact'"));
    }
}
