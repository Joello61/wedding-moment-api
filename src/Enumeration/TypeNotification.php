<?php

namespace App\Enumeration;

enum TypeNotification: string
{
    case RSVP_CONFIRMATION = 'rsvp_confirmation';
    case NOUVEAU_MESSAGE = 'nouveau_message';
    case MISE_A_JOUR_PROGRAMME = 'mise_a_jour_programme';
    case RAPPEL_EVENEMENT = 'rappel_evenement';
}
