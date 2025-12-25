<?php

namespace App\Filament\Teacher\Resources\Courses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use App\Models\Member;
use App\Models\CourseType;
use Illuminate\Support\Facades\Auth;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('teacher_name')
                ->label('Teacher')
                ->default(fn() => Auth::user()->name)
                ->disabled()
                ->dehydrated(false),
                  
                Hidden::make('user_token')
                    ->default(fn() => Auth::user()->token),

                TextInput::make('name')
                    ->required(),

                Select::make('type_id')
                    ->label('Course Type')
                    ->options(CourseType::all()->pluck('title', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                FileUpload::make('thumbnail')
                    ->disk('public')
                    ->directory('course-thumbnails')
                    ->visibility('public')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(true),
                FileUpload::make('video')
                    ->disk('public')
                    ->maxSize(51200000)
                    ->directory('course-videos')
                    ->default(null)
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'video/mp4',
                        'video/mov',
                        'video/avi',
                        'video/wmv',
                        'video/mp3',
                        'video/m4a',
                        'video/wma'
                    ]),
                Textarea::make('description')
                    ->columnSpanFull(),

                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('lesson_num')
                    ->numeric(),
                TextInput::make('video_length')
                    ->numeric(),
                TextInput::make('follow')
                    ->numeric(),
                TextInput::make('score')
                    ->numeric(),

            ]);
    }
}
