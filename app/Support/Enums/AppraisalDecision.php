<?php

namespace App\Support\Enums;

enum AppraisalDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Deferred = 'deferred';
}
