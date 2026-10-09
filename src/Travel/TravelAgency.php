<?php

namespace App\Travel;

use App\Entity\Proposal;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Books the trips of speakers with the travel agency of the conferences.
 */
final class TravelAgency
{
    public function __construct(
        #[Target('travel_agency.client')]
        private readonly HttpClientInterface $client,
    ) {
    }

    /**
     * @return string The reference of the booking
     */
    public function book(Proposal $proposal): string
    {
        $response = $this->client->request('POST', 'bookings', [
            'json' => [
                'event' => $proposal->conference->name,
                'city' => $proposal->conference->city,
                'arrival' => $proposal->conference->startsOn->modify('-1 day')->format('Y-m-d'),
                'departure' => $proposal->conference->endsOn->modify('+1 day')->format('Y-m-d'),
                'travelers' => $proposal->speakers->map(static fn ($speaker) => ['name' => $speaker->name, 'email' => $speaker->email])->getValues(),
            ],
        ]);

        return $response->toArray()['reference'];
    }
}
