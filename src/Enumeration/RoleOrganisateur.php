<?php

namespace App\Enumeration;

enum RoleOrganisateur: string
{
    case SCANNEUR = 'scanneur';
    case ORGANISATEUR = 'organisateur';
    case PHOTOGRAPHE = 'photographe';
}
