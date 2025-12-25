<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('name'),
                TextEntry::make('email')
                    ->label('Email address'),
                ImageEntry::make('avatar_url')
                    ->disk('s3')
                    ->imageHeight(100),

                TextEntry::make('open_id'),
                TextEntry::make('token'),
                TextEntry::make('age')
                    ->numeric(),
                TextEntry::make('description'),
                TextEntry::make('job'),
            ]);
    }
}
