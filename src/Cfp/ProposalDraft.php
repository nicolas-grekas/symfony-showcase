<?php

namespace App\Cfp;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class ProposalDraft
{
    #[Ignore]
    public string $currentStep = '';

    // the API validates the whole draft in the groups of all the steps, while the form flow validates the object
    // of the current step by itself: scoping each Valid to its step keeps the flow off the other steps
    #[Assert\Valid(groups: ['speaker'], restrictGroups: false)]
    public SpeakerData $speaker;

    #[Assert\Valid(groups: ['talk'], restrictGroups: false)]
    public TalkData $talk;

    /** @var list<MaterialData> */
    #[Assert\Valid(groups: ['materials'], restrictGroups: false)]
    public array $materials = [];

    public function __construct()
    {
        $this->speaker = new SpeakerData();
        $this->talk = new TalkData();
    }
}
