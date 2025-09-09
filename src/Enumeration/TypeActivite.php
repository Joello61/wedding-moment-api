<?php

namespace App\Enumeration;

enum TypeActivite: string
{
    case CEREMONIE = 'ceremonie';
    case COCKTAIL = 'cocktail';
    case REPAS = 'repas';
    case SOIREE = 'soiree';
    case AUTRE = 'autre';
}
