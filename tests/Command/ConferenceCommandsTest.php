<?php

namespace App\Tests\Command;

use App\Entity\Conference;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;

final class ConferenceCommandsTest extends KernelTestCase
{
    private ApplicationTester $tester;

    protected function setUp(): void
    {
        $application = new Application(static::bootKernel());
        $application->setAutoExit(false);
        $this->tester = new ApplicationTester($application);
    }

    public function testListAsASubCommand()
    {
        $this->tester->run(['command' => 'conference', 'list']);

        $this->tester->assertCommandIsSuccessful();
        $this->assertMatchesRegularExpression('/symfonycon-amsterdam +SymfonyCon Amsterdam .+ closed +2 \/ 2/', $this->tester->getDisplay());
    }

    public function testImportSeveralFiles()
    {
        $dir = sys_get_temp_dir().'/conferences-'.bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (['paris' => 'Paris', 'berlin' => 'Berlin'] as $slug => $city) {
            file_put_contents("$dir/$slug.json", json_encode([[
                'name' => "SymfonyLive $city", 'slug' => "symfonylive-$slug", 'city' => $city,
                'startsOn' => '2027-03-26', 'endsOn' => '2027-03-27', 'cfpOpensAt' => '2026-10-01 00:00', 'cfpClosesAt' => '2026-12-01 00:00',
            ]]));
        }

        $this->tester->run(['command' => 'conference:import', 'files' => ["$dir/paris.json", "$dir/berlin.json"]]);

        $this->tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('Imported 2 conference(s) from 2 file(s).', $this->tester->getDisplay());
        $this->assertNotNull(static::getContainer()->get(EntityManagerInterface::class)->getRepository(Conference::class)->findOneBy(['slug' => 'symfonylive-berlin']));
        array_map(unlink(...), glob("$dir/*"));
        rmdir($dir);
    }

    public function testFilterTicketsByStatuses()
    {
        $this->tester->run(['command' => 'conference:tickets', 'slug' => 'symfonycon-amsterdam', '--status' => ['pending', 'expired']]);
        $this->tester->assertCommandIsSuccessful();
        $this->assertStringNotContainsString('@example.com', $this->tester->getDisplay());

        $this->tester->run(['command' => 'conference:tickets', 'slug' => 'symfonycon-amsterdam', '--status' => ['paid']]);
        $this->assertStringContainsString('eve@example.com', $this->tester->getDisplay());

        $this->tester->run(['command' => 'conference:tickets', 'slug' => 'symfonycon-amsterdam', '--status' => ['bogus']]);
        $this->assertSame(1, $this->tester->getStatusCode());
        // errors are split at the width of the terminal, words included
        $this->assertStringContainsString('Supportedvaluesare"pending","paid","expired".', preg_replace('/\s+/', '', $this->tester->getDisplay()));
    }
}
