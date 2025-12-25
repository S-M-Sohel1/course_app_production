<?php

namespace App\Filament\Resources\Members\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
class MemberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->default(null),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                    TextInput::make('password')
                    ->password()
                    ->required(fn (string $context): bool => $context === 'create')
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->maxLength(255)
                    ->label('Password')
                    ->helperText('Leave blank to keep current password'),
                    Select::make('role')
                    ->options([
                        'teacher' => 'Teacher',
                        'student' => 'Student',
                    ])
                    ->required()
                    ->default('teacher')
                    ->native(false),


                          FileUpload::make('avatar')
                            ->label('Avatar')
                            ->placeholder('Select image')
                            ->disk('s3')
                            ->directory('avatars')
                            ->visibility('public')
                            ->dehydrated(true),
               
                TextInput::make('open_id')
                    ->default(null),
                TextInput::make('token')
                    ->default(null),
            
              
                TextInput::make('description')
                    ->default(null),
                TextInput::make('job')
                    ->default(null),
            ]);
    }
}
