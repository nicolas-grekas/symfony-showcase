<?php

namespace App\Cfp;

final class ProposalDraft
{
    public string $currentStep = '';
    public SpeakerData $speaker;
    public TalkData $talk;

    /** @var list<MaterialData> */
    public array $materials = [];

    public function __construct()
    {
        $this->speaker = new SpeakerData();
        $this->talk = new TalkData();
    }
}
