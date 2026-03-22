<?php

namespace App\Enum;

enum ResponseMode: string
{
    case Direct = 'direct';
    case Hybrid = 'hybrid';
}