<?php

namespace App\Enumeration;

enum StatutCouple: string
{
    case ACTIF = 'actif';
    case SUSPENDU = 'suspendu';
    case ARCHIVE = 'archive';
}
