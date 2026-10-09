<?php

namespace App\Cfp;

use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Validator\Constraints as Assert;

final class ProposalDraft
{
    #[Ignore]
    public string $currentStep = '';

    // the form flow validates the "Default" group and the one of the current step: each nested object is
    // validated on its own step only, and then against the "Default" group too (restrictGroups: false)
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
