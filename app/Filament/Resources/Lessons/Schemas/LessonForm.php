<?php

namespace App\Filament\Resources\Lessons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\Course;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Hidden;

class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('course_id')
                    ->label('Course')
                    ->options(Course::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function ($set, $state) {
                        if ($state) {
                            $course = Course::find($state);
                            if ($course) {
                                $set('user_token', $course->user_token);
                            }
                        }
                    }),

                Hidden::make('user_token'),
                TextInput::make('name')
                    ->required(),

                FileUpload::make('thumbnail')
                    ->directory('video-thumbnails')
                    ->visibility('public')
                    ->required(fn(string $context): bool => $context === 'create')
                    ->dehydrated(true),


                Repeater::make('video')
                    ->label('Videos')
                    ->schema([
                        TextInput::make('name')
                            ->label('Video Name')
                            ->placeholder('Input Name')
                            ->required()
                            ->columnSpan(1),
                        FileUpload::make('thumbnail')
                            ->label('Thumbnail')
                            ->placeholder('Select image')
                            ->directory('lesson-thumbnails')
                            ->visibility('public')
                            ->dehydrated(true),
                        FileUpload::make('url')
                            ->label('video')
                            ->maxSize(512000)
                            ->disk('local') // Use local disk instead of S3
                            ->directory('lesson-videos-temp') // Temporary local directory
                            ->visibility('public')
                            ->dehydrated(true)
                            ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', 'video/wmv', 'video/mp3', 'video/m4a', 'video/wma']),

                    ])
                    ->columns(3)
                    ->defaultItems(0)
                    ->addActionLabel('New')
                    ->collapsible()
                    ->itemLabel(fn(array $state): ?string => $state['name'] ?? 'Video')
                    ->columnSpanFull(),




                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }
}
