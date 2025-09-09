<?php

namespace App\Enumeration;

enum StatutRsvp: string
{
    case EN_ATTENTE = 'en_attente';
    case CONFIRME = 'confirme';
    case DECLINE = 'decline';
    case PEUT_ETRE = 'peut_etre';
}
