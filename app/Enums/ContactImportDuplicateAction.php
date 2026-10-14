<?php

namespace App\Enums;

enum ContactImportDuplicateAction: string
{
    case Skip = 'skip';
    case Update = 'update';
}
