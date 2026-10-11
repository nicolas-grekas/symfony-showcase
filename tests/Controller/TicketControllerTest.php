<?php

namespace App\Tests\Controller;

use App\Entity\Conference;
use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Tests\MessengerAssertionsTrait;
use App\Webhook\StandardWebhooksSignature;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\RemoteEvent\Messenger\ConsumeRemoteEventMessage;

final class TicketControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use MessengerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // limiters keep their state in a cache pool, which outlives the tests
        static::getContainer()->get('cache.rate_limiter')->clear();
    }

    public function testBuyingATicketHoldsASeatUntilThePaymentIsConfirmed()
    {
        $this->client->loginUser($this->findUser('alice@example.com'));
        $this->client->request('GET', '/conferences/symfonylive-lille/tickets');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.price', '€199.00');

        $this->client->submitForm('Buy a ticket');
        $this->assertResponseStatusCodeSame(303);
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '4');

        $ticket = $this->findTicket('alice@example.com');
        $this->assertSame(TicketStatus::Pending, $ticket->status);
        $this->assertSame(19900, $ticket->price);

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringStartsWith($_SERVER['PAYMENT_CHECKOUT_URL'].'?', $location);
        parse_str(parse_url($location, \PHP_URL_QUERY), $query);
        $this->assertSame((string) $ticket->id, $query['reference']);
        $this->assertSame('19900', $query['amount']);

        // the provider reports the payment with a webhook, consumed asynchronously
        $this->sendPaymentWebhook('payment.succeeded', $ticket);
        $this->assertResponseStatusCodeSame(202);
        $this->assertQueuedMessageCount(1, 'async', ConsumeRemoteEventMessage::class);
        static::consumeQueuedMessages('async', 1);

        $ticket = $this->findTicket('alice@example.com');
        $this->assertSame(TicketStatus::Paid, $ticket->status);
        $this->assertSame('pay_123', $ticket->paymentReference);

        // the ticket is sent by email, behind a signed link that expires with the conference
        $this->assertQueuedMessageCount(1, 'async', SendEmailMessage::class);
        $link = $this->extractTicketLink();
        $this->assertStringContainsString('_expiration=', $link);

        $this->client->request('GET', $link);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'SymfonyLive Lille');
        $this->assertSelectorTextContains('.holder', 'Alice Martin');

        $this->client->request('GET', '/tickets/'.$ticket->id);
        $this->assertResponseStatusCodeSame(404);

        static::mockTime('+100 days');
        $this->client->request('GET', $link);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDuplicatedWebhooksAreConsumedOnce()
    {
        $ticket = $this->holdTicket('alice@example.com', 'symfonylive-lille');

        $this->sendPaymentWebhook('payment.succeeded', $ticket);
        static::consumeQueuedMessages('async', 1);
        $this->assertQueuedMessageCount(1, 'async', SendEmailMessage::class);

        $this->sendPaymentWebhook('payment.succeeded', $ticket);
        static::consumeQueuedMessages('async', 1);
        $this->assertSame(TicketStatus::Paid, $this->findTicket('alice@example.com')->status);
        $this->assertQueuedMessageCount(0, 'async', SendEmailMessage::class);
    }

    public function testAFailedPaymentReleasesTheSeat()
    {
        $ticket = $this->holdTicket('alice@example.com', 'symfonylive-lille');

        $this->sendPaymentWebhook('payment.failed', $ticket);
        static::consumeQueuedMessages('async');

        $this->assertSame(TicketStatus::Expired, $this->findTicket('alice@example.com')->status);
    }

    public function testWebhooksWithAWrongSignatureAreRejected()
    {
        $ticket = $this->holdTicket('alice@example.com', 'symfonylive-lille');

        $this->sendPaymentWebhook('payment.succeeded', $ticket, 'whsec_'.base64_encode('not the secret'));

        $this->assertResponseStatusCodeSame(406);
        $this->assertQueuedMessageCount(0, 'async');
    }

    public function testASoldOutConferenceSellsNoMoreTickets()
    {
        $this->client->loginUser($this->findUser('alice@example.com'));
        $this->client->request('GET', '/conferences/symfonycon-amsterdam/tickets');
        $this->assertSelectorTextContains('main', 'Sold out');

        $this->client->request('POST', '/conferences/symfonycon-amsterdam/tickets', ['_token' => $this->csrfToken()]);
        $this->assertResponseStatusCodeSame(409);
    }

    public function testBuyersAreRateLimited()
    {
        $this->client->loginUser($this->findUser('alice@example.com'));
        $token = $this->csrfToken();

        // the seat held by the first attempt is offered again to the next ones
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/conferences/symfonylive-lille/tickets', ['_token' => $token]);
            $this->assertResponseStatusCodeSame(303);
        }
        $this->assertResponseHeaderSame('X-RateLimit-Remaining', '0');
        $this->assertCount(1, $this->entityManager()->getRepository(Ticket::class)->findBy(['buyer' => $this->findUser('alice@example.com')]));

        $this->client->request('POST', '/conferences/symfonylive-lille/tickets', ['_token' => $token]);
        $this->assertResponseStatusCodeSame(429);
        $this->assertResponseHasHeader('Retry-After');
    }

    public function testOrganizersSeeTheSalesOfTheLastDays()
    {
        $this->client->loginUser($this->findUser('organizer@example.com'));
        $conference = $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => 'symfonycon-amsterdam']);
        $this->client->request('GET', '/admin/conferences/'.$conference->id.'/sales');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.sold', '2 / 2');
        $this->assertSelectorTextContains('.revenue', '€698.00');
        $this->assertSelectorCount(7, 'table.sales tbody tr');
    }

    private function holdTicket(string $email, string $slug): Ticket
    {
        $conference = $this->entityManager()->getRepository(Conference::class)->findOneBy(['slug' => $slug]);
        $ticket = new Ticket($conference, $this->findUser($email), static::getContainer()->get('clock')->now());
        $this->entityManager()->persist($ticket);
        $this->entityManager()->flush();

        return $ticket;
    }

    private function sendPaymentWebhook(string $type, Ticket $ticket, ?string $secret = null): void
    {
        $id = 'msg_'.bin2hex(random_bytes(8));
        $timestamp = (string) time();
        $body = json_encode(['type' => $type, 'data' => ['reference' => (string) $ticket->id, 'payment' => 'pay_123', 'amount' => $ticket->price]]);
        $signature = StandardWebhooksSignature::sign($id, $timestamp, $body, $secret ?? $_SERVER['PAYMENTS_WEBHOOK_SECRET']);

        $this->client->request('POST', '/webhook/payments', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_WEBHOOK_ID' => $id,
            'HTTP_WEBHOOK_TIMESTAMP' => $timestamp,
            'HTTP_WEBHOOK_SIGNATURE' => $signature,
        ], content: $body);
    }

    private function extractTicketLink(): string
    {
        // the email is rendered when the worker sends it
        static::consumeQueuedMessages('async');
        $sent = array_values(array_filter(static::getMailerEvents(), static fn ($event) => !$event->isQueued()));
        $email = $sent[0]->getMessage();
        $this->assertSame('Your ticket for SymfonyLive Lille', $email->getSubject());
        $this->assertMatchesRegularExpression('{href="(http://localhost/tickets/[^"]++)"}', $email->getHtmlBody());
        preg_match('{href="(http://localhost/tickets/[^"]++)"}', $email->getHtmlBody(), $m);

        return html_entity_decode($m[1]);
    }

    private function csrfToken(): string
    {
        $this->client->request('GET', '/conferences/symfonylive-lille/tickets');

        return $this->client->getCrawler()->filter('input[name="_token"]')->attr('value') ?? '';
    }

    private function findTicket(string $email): Ticket
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Ticket::class)->findOneBy(['buyer' => $this->findUser($email)]);
    }

    private function findUser(string $email): User
    {
        return $this->entityManager()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
