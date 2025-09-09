<?php

namespace App\Enumeration;

enum TypeInvite: string
{
    case FAMILLE = 'famille';
    case AMI = 'ami';
    case COLLEGUE = 'collegue';
    case AUTRE = 'autre';
}
