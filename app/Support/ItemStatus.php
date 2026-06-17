<?php

namespace App\Support;

enum ItemStatus: string
{
    case New = 'new';
    case Used = 'used';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
