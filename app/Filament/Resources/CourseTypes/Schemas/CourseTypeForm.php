<?php

namespace App\Filament\Resources\CourseTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CourseTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->default(null),
                TextInput::make('parent_id')
                    ->default(null),
                TextInput::make('description')
                    ->default(null),
                TextInput::make('order')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
