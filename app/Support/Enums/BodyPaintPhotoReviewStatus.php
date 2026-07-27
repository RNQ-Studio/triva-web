<?php

namespace App\Support\Enums;

enum BodyPaintPhotoReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
