<?php

namespace App\Message;

use Symfony\Component\Uid\Uuid;

interface TravelCommand
{
    public Uuid $proposalId { get; }
}
