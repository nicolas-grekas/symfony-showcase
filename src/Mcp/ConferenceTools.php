<?php

namespace App\Mcp;

use App\Cfp\ProposalDraft;
use App\Cfp\ProposalSubmitter;
use App\Entity\Conference;
use App\Entity\Proposal;
use App\Entity\User;
use App\Enum\Format;
use App\Enum\Level;
use App\Repository\ConferenceRepository;
use App\Repository\ProposalRepository;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockAwareTrait;
use Symfony\Component\ObjectMapper\ObjectMapperInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The tools of the MCP server. The firewall of the API authenticates every request.
 */
final class ConferenceTools
{
    use ClockAwareTrait;

    public function __construct(
        private readonly ConferenceRepository $conferences,
        private readonly ProposalRepository $proposals,
        private readonly ProposalSubmitter $submitter,
        private readonly ObjectMapperInterface $objectMapper,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    /**
     * Searches the accepted talks of all conferences, in their title and abstract.
     *
     * @param string $query Words to look for, case-insensitive
     *
     * @return array{talks: list<TalkResult>}
     */
    #[McpTool(name: 'search_talks', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function searchTalks(string $query): array
    {
        return ['talks' => $this->map($this->proposals->searchAccepted($query), TalkResult::class)];
    }

    /**
     * Gives the schedule of a conference: the time, room, title and speakers of each scheduled talk.
     *
     * @param string $conference The slug of the conference, e.g. "symfonylive-lille"
     *
     * @return array{conference: string, slots: list<SlotResult>}
     */
    #[McpTool(name: 'get_schedule', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function getSchedule(string $conference): array
    {
        $conference = $this->findConference($conference);

        return ['conference' => $conference->name, 'slots' => $this->map($this->proposals->findScheduled($conference), SlotResult::class)];
    }

    /**
     * Lists the proposals of the speaker who is logged in, with their status.
     *
     * @return array{proposals: list<ProposalResult>}
     */
    #[McpTool(name: 'list_my_proposals', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function listMyProposals(): array
    {
        return ['proposals' => $this->map($this->proposals->findBySpeakerEmail($this->getUser()->email), ProposalResult::class)];
    }

    /**
     * Submits a talk to the call for papers of a conference, in the name of the speaker who is logged in.
     *
     * @param string      $conference  The slug of the conference, e.g. "symfonylive-lille"
     * @param string      $title       The title of the talk, 150 characters at most
     * @param string      $abstract    What the talk is about, 50 characters at least
     * @param Level       $level       The level of the audience
     * @param Format      $format      A talk lasts 40 minutes, a workshop 3 hours and a lightning talk 10 minutes
     * @param string      $speakerName The name of the speaker, as it will appear on the website
     * @param string      $bio         A few words about the speaker
     * @param string|null $company     The company of the speaker
     */
    #[McpTool(name: 'submit_proposal')]
    #[IsGranted('OAUTH2_SCOPE(proposals:write)')]
    public function submitProposal(string $conference, string $title, string $abstract, Level $level, Format $format, string $speakerName, string $bio, ?string $company = null): ProposalResult
    {
        $conference = $this->findConference($conference);
        if (!$conference->isCfpOpen($this->now())) {
            throw new ToolCallException(\sprintf('The call for papers of %s is closed.', $conference->name));
        }

        $draft = new ProposalDraft();
        $draft->speaker->name = $speakerName;
        $draft->speaker->bio = $bio;
        $draft->speaker->company = $company;
        $draft->talk->title = $title;
        $draft->talk->abstract = $abstract;
        $draft->talk->level = $level;
        $draft->talk->format = $format;

        // the groups of the steps of the form flow, as the API validates them
        if (\count($violations = $this->validator->validate($draft, groups: ['Default', 'speaker', 'talk', 'materials']))) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }

            throw new ToolCallException('The proposal is not valid: '.implode(' ', $errors));
        }

        return $this->objectMapper->map($this->submitter->submit($conference, $draft, $this->getUser()), ProposalResult::class);
    }

    private function findConference(string $slug): Conference
    {
        return $this->conferences->findOneBy(['slug' => $slug]) ?? throw new ToolCallException(\sprintf('There is no conference "%s". The upcoming conferences are: %s.', $slug, implode(', ', array_map(static fn (Conference $c) => $c->slug, $this->conferences->findUpcoming($this->now())))));
    }

    private function getUser(): User
    {
        return $this->security->getUser();
    }

    /**
     * @template T of object
     *
     * @param list<Proposal> $proposals
     * @param class-string<T> $class
     *
     * @return list<T>
     */
    private function map(array $proposals, string $class): array
    {
        return array_map(fn (Proposal $proposal) => $this->objectMapper->map($proposal, $class), $proposals);
    }
}
