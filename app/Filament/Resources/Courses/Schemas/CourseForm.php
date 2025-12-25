<?php

namespace App\Filament\Resources\Courses\Schemas;

use App\Models\CourseType;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

use Filament\Forms\Components\FileUpload;
use App\Models\Member;
use Filament\Forms\Components\Toggle;
class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('user_token')
                    ->label('Teacher')
                    ->options(Member::all()->pluck('name', 'token'))

                    ->searchable()
                    ->preload(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                // Select with searchable options from CourseType table
                Select::make('type_id')
                    ->label('Course Type')
                    ->options(CourseType::all()->pluck('title', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                FileUpload::make('thumbnail')
                    ->disk('s3')
                    ->directory('course-thumbnails')
                    ->visibility('public')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(true),
                    

                FileUpload::make('video')
                    ->disk('s3')
                    ->maxSize(51200000)
                    ->directory('course-videos')
                    ->default(null)
                    ->visibility('public')
                    ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', 'video/wmv', 'video/mp3', 'video/m4a', 'video/wma']),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('price')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('lesson_num')
                    ->numeric()
                    ->default(0),
                

                TextInput::make('video_length')
                    ->label('Video Length (minutes)')
                    ->numeric()
                    ->default(0),

                TextInput::make('follow')
                    ->numeric()
                    ->default(0),

                TextInput::make('score')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(5)
                    ->step(0.1)
                    ->default(0),

                    Toggle::make('recommended')
                    ->label('Recommended')
                    ->default(false)
                    ->onColor('success')
                    ->offColor('gray')
                    ->onIcon('heroicon-o-check')
                    ->offIcon('heroicon-o-x-mark'),

            ]);
    }
}
