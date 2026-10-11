<?php

namespace App\Cfp;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class ProposalDraft
{
    #[Ignore]
    public string $currentStep = '';

    // the API validates the whole draft in the groups of all the steps, while the form flow validates the object
    // of the current step by itself: scoping each cascade to its step keeps the flow off the other steps
    #[Assert\Callback([self::class, 'validateStep'], groups: ['speaker'])]
    public SpeakerData $speaker;

    #[Assert\Callback([self::class, 'validateStep'], groups: ['talk'])]
    public TalkData $talk;

    /** @var list<MaterialData> */
    #[Assert\Callback([self::class, 'validateStep'], groups: ['materials'])]
    public array $materials = [];

    public function __construct()
    {
        $this->speaker = new SpeakerData();
        $this->talk = new TalkData();
    }

    /**
     * Validates the data of a step against its own constraints, which a Valid constraint scoped to the group
     * of the step would check in that group only.
     */
    public static function validateStep(object|array $data, ExecutionContextInterface $context): void
    {
        $context->getValidator()->inContext($context)->validate($data, null, 'Default');
    }
}
