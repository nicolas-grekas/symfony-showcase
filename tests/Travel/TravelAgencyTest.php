<?php

namespace App\Tests\Travel;

use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\Speaker;
use App\Enum\Format;
use App\Enum\Level;
use App\Tests\HarRecorder;
use App\Travel\TravelAgency;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * Replays the exchanges recorded with the travel agency; run with RECORD_HAR=1 while the server runs to record them again.
 */
final class TravelAgencyTest extends KernelTestCase
{
    public function testBookTheTravelOfTheSpeakers()
    {
        static::getContainer()->set('http_client.transport', new MockHttpClient(new HarRecorder(__DIR__.'/TravelAgencyTest/testBookTheTravelOfTheSpeakers.har')));

        $conference = new Conference('SymfonyCon Amsterdam', 'symfonycon-amsterdam', 'Amsterdam', new DatePoint('2027-11-25'), new DatePoint('2027-11-26'), new DatePoint('2027-03-01'), new DatePoint('2027-06-01'));
        $proposal = new Proposal($conference, 'Workflows as code', str_repeat('Workflows as code. ', 6), Level::Intermediate, Format::Talk);
        $speaker = new Speaker('alice@example.com');
        $speaker->name = 'Alice Martin';
        $proposal->addSpeaker($speaker);

        $this->assertMatchesRegularExpression('/^TRV-[0-9A-F]{8}$/', static::getContainer()->get(TravelAgency::class)->book($proposal));
    }
}
