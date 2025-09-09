<?php

namespace App\Enumeration;

enum TypeSondage: string
{
    case CHOIX_UNIQUE = 'choix_unique';
    case CHOIX_MULTIPLE = 'choix_multiple';
    case TEXTE_LIBRE = 'texte_libre';
}
