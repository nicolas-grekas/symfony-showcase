<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConferenceControllerTest extends WebTestCase
{
    public function testHomeListsUpcomingConferences()
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, 'article h2');
        $this->assertAnySelectorTextContains('article h2', 'SymfonyLive Lille');
    }

    public function testConferencePageListsAcceptedTalksOnly()
    {
        $client = static::createClient();
        $client->request('GET', '/conferences/symfonycon-amsterdam');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorCount(2, 'article h3');
        $this->assertAnySelectorTextContains('article h3', 'Workflows as code');
        $this->assertAnySelectorTextNotContains('article h3', 'Why my cache was cold');
    }
}
