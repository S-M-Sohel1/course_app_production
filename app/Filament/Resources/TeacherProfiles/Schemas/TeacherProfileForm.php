<?php

namespace App\Filament\Resources\TeacherProfiles\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\Member;
use Filament\Forms\Components\FileUpload;
class TeacherProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
              
                FileUpload::make('avatar')
                    ->disk('s3')
                    ->directory('teacher-avatars')
                    ->visibility('public')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(true),
                    
               
                Select::make('user_token')
                    ->label('Member')
                    ->options(Member::where('role', 'teacher')->pluck('name', 'token'))
                    ->searchable()
                    ->preload(),
                
                FileUpload::make('cover')
                    ->disk('s3')
                    ->directory('teacher-covers')
                    ->visibility('public')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(true),
                
                TextInput::make('rating')
                    ->numeric()
                    ->default(0),
                TextInput::make('downloads')
                    ->numeric()
                    ->default(0),
                TextInput::make('total_students')
                    ->numeric()
                    ->default(0),
                TextInput::make('experience_years')
                    ->numeric()
                    ->default(0),
                TextInput::make('job')
                    ->default(null),


              
            
            ]);
    }
}
