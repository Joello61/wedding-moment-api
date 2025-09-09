<?php

namespace App\Enumeration;

enum StatutBlogPost: string
{
    case BROUILLON = 'brouillon';
    case PUBLIE = 'publie';
    case ARCHIVE = 'archive';
}
