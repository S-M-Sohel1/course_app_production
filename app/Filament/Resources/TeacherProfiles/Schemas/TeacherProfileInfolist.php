<?php

namespace App\Filament\Resources\TeacherProfiles\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
class TeacherProfileInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                ImageEntry::make('avatar')
                ->disk('s3')
                ->visibility('public')
                
                ->imageHeight(100),
                ImageEntry::make('cover')
                ->disk('s3')
                ->visibility('public')
                
                ->imageHeight(100),
                TextEntry::make('rating'),
                TextEntry::make('downloads'),
                TextEntry::make('total_students'),
                TextEntry::make('experience_years'),
                TextEntry::make('job'),
            ]);
    }
}
