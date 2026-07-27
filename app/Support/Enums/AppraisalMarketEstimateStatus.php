<?php

namespace App\Support\Enums;

enum AppraisalMarketEstimateStatus: string
{
    case Ready = 'ready';
    case Insufficient = 'insufficient';
    case Failed = 'failed';
}
