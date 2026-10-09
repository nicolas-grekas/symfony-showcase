<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConferenceApiTest extends WebTestCase
{
    public function testListUpcomingConferences()
    {
        $client = static::createClient();
        $client->request('GET', '/api/conferences');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=300, public');
        // RFC 9652 sends the template as a structured field string
        $this->assertResponseHeaderSame('Link-Template', '"/api/conferences/{slug}"; rel="item"');

        $conferences = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame(['symfonycon-amsterdam', 'symfonylive-lille'], array_column($conferences, 'slug'));
        $this->assertSame(['id', 'name', 'slug', 'city', 'startsOn', 'endsOn'], array_keys($conferences[0]));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $conferences[0]['startsOn']);
    }

    public function testShowAConferenceWithItsProgram()
    {
        $client = static::createClient();
        $client->request('GET', '/api/conferences/symfonycon-amsterdam');

        $this->assertResponseIsSuccessful();
        $conference = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Amsterdam', $conference['city']);
        $this->assertArrayHasKey('cfpClosesAt', $conference);
        $this->assertSame(['Profiling a Symfony app end to end', 'Workflows as code'], array_column($conference['talks'], 'title'));
        $this->assertSame(['level' => 'advanced', 'format' => 'workshop', 'speakers' => ['Bob Durand']], array_intersect_key($conference['talks'][0], array_flip(['level', 'format', 'speakers'])));
    }

    public function testUnknownConferencesAreNotFound()
    {
        $client = static::createClient();
        $client->request('GET', '/api/conferences/nope');

        $this->assertResponseStatusCodeSame(404);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');
        $this->assertSame(404, json_decode($client->getResponse()->getContent(), true)['status']);
    }

    public function testStreamTheTalksOfAConference()
    {
        $client = static::createClient();
        $client->request('GET', '/api/conferences/symfonycon-amsterdam/talks');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('CDN-Cache-Control', 'max-age=3600');
        $talks = json_decode($client->getInternalResponse()->getContent(), true);
        $this->assertSame(['Profiling a Symfony app end to end', 'Workflows as code'], array_column($talks, 'title'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $talks[0]['id']);
    }

    public function testTheApiCatalogListsTheEndpoints()
    {
        $client = static::createClient();
        $client->request('GET', '/.well-known/api-catalog');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/linkset+json; profile="https://www.rfc-editor.org/info/rfc9727"');
        $linkset = json_decode($client->getResponse()->getContent(), true)['linkset'];
        $this->assertSame(['anchor' => 'http://localhost/.well-known/api-catalog', 'item' => [['href' => 'http://localhost/api/conferences']]], $linkset[0]);
        $this->assertSame('http://localhost/api/conferences', $linkset[1]['anchor']);
        $this->assertSame('http://localhost/api/docs', $linkset[1]['service-doc'][0]['href']);
    }
}
