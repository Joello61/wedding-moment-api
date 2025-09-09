<?php

namespace App\Enumeration;

enum TypeUtilisateur: string
{
    case COUPLE = 'couple';
    case INVITE = 'invite';
    case ORGANISATEUR = 'organisateur';
}
