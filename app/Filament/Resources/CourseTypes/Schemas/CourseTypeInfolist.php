<?php

namespace App\Filament\Resources\CourseTypes\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

class CourseTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),

                TextEntry::make('parent_id'),

                TextEntry::make('description'),

                TextEntry::make('order'),

            ]);
    }
}
