<?php

namespace App\Support\Enums;

enum AppraisalPhotoReviewStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
