<?php

namespace App\Enumeration;

enum StatutSuggestion: string
{
    case EN_ATTENTE = 'en_attente';
    case APPROUVEE = 'approuvee';
    case REFUSEE = 'refusee';
}
