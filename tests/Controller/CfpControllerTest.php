<?php

namespace App\Tests\Controller;

use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\ProposalStatus;
use App\Message\ProposalSubmitted;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CfpControllerTest extends WebTestCase
{
    public function testSubmitAProposalStepByStep()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('alice@example.com'));
        $client->request('GET', '/conferences/symfonylive-lille/cfp');
        $this->assertResponseIsSuccessful();
        $this->assertInputValueSame('proposal_flow[speaker][name]', 'Alice Martin');

        $client->submitForm('Next', [
            'proposal_flow[speaker][bio]' => 'Alice writes about forms.',
        ]);
        $this->assertSelectorTextContains('ol.steps li.current li.current', 'Talk');

        $client->submitForm('Next', [
            'proposal_flow[talk][title]' => 'Form flows in practice',
            'proposal_flow[talk][abstract]' => str_repeat('Multi-step forms with the FormFlow API. ', 3),
            'proposal_flow[talk][level]' => 'intermediate',
            'proposal_flow[talk][format]' => 'talk',
        ]);
        $this->assertSelectorTextContains('ol.steps li.current li.current', 'Materials');

        // entries are added by JavaScript from the prototypes, so they are posted by hand
        $form = $client->getCrawler()->selectButton('Next')->form();
        $values = $form->getPhpValues();
        $values['proposal_flow']['materials'] = [
            ['url' => 'https://speakerdeck.com/carol/forms'],
            ['url' => 'https://youtube.com/watch?v=abc', 'minutes' => '35'],
        ];
        $client->request($form->getMethod(), $form->getUri(), $values);
        $this->assertSelectorTextContains('ol.steps > li.current', 'Review');

        $client->submitForm('Finish', ['proposal_flow[review]' => true]);
        $this->assertResponseRedirects();

        // checked before the next request, which resets the in-memory transports:
        // the event waits in the outbox, which forwards it to RabbitMQ once the transaction commits
        $this->assertQueuedMessageCount(1, 'outbox', ProposalSubmitted::class);
        static::consumeQueuedMessages('outbox');
        $this->assertQueuedMessageCount(1, 'events', ProposalSubmitted::class);

        // its handlers email the three people who review proposals, then the speaker
        static::consumeQueuedMessages('events');
        $this->assertQueuedMessageCount(4, 'async', SendEmailMessage::class);

        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Form flows in practice');

        $proposal = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Proposal::class)->findOneBy(['title' => 'Form flows in practice']);
        $this->assertSame(ProposalStatus::Submitted, $proposal->getStatus());
        $this->assertNotNull($proposal->submittedAt);
        $this->assertCount(2, $proposal->materials);
    }

    public function testEachStepIsValidated()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('alice@example.com'));
        $client->request('GET', '/conferences/symfonylive-lille/cfp');
        $client->submitForm('Next', [
            'proposal_flow[speaker][name]' => '',
            'proposal_flow[speaker][bio]' => 'Alice writes about forms.',
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('ol.steps > li.current', 'Speaker');
    }

    public function testTheCodeOfConductMustBeAccepted()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('alice@example.com'));
        $client->request('GET', '/conferences/symfonylive-lille/cfp');
        $client->submitForm('Next', [
            'proposal_flow[speaker][bio]' => 'Alice writes about forms.',
        ]);
        $client->submitForm('Next', [
            'proposal_flow[talk][title]' => 'Form flows in practice',
            'proposal_flow[talk][abstract]' => str_repeat('Multi-step forms with the FormFlow API. ', 3),
            'proposal_flow[talk][level]' => 'beginner',
            'proposal_flow[talk][format]' => 'lightning',
        ]);
        $client->submitForm('Next');
        $client->submitForm('Finish');

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('ol.steps > li.current', 'Review');
    }

    public function testTheCallForPapersOfAPastConferenceIsClosed()
    {
        $client = static::createClient();
        $client->loginUser($this->findUser('alice@example.com'));
        $client->request('GET', '/conferences/symfonycon-amsterdam/cfp');

        $this->assertResponseStatusCodeSame(410);
    }

    public function testSubmittingRequiresToLogIn()
    {
        $client = static::createClient();
        $client->request('GET', '/conferences/symfonylive-lille/cfp');

        $this->assertResponseRedirects('/login');
    }

    private function findUser(string $email): User
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => $email]);
    }
}
