<?php

namespace App\Enumeration;

enum TypeGalerie: string
{
    case AVANT_MARIAGE = 'avant_mariage';
    case JOUR_J = 'jour_j';
    case APRES_MARIAGE = 'apres_mariage';
    case LIVE_FEED = 'live_feed';
}
