<?php

namespace App\Filament\Teacher\Resources\Lessons\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\Course;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
class LessonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
        ->components([

            Select::make('course_id')
            ->label('Course')
            ->options(Course::where('user_token', Auth::user()->token)->pluck('name', 'id'))
            ->searchable()
            ->preload(),
            Hidden::make('user_token')
                    ->default(fn() => Auth::user()->token),
           


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
                        ->image()
                       
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->columnSpan(1),
                        FileUpload::make('url')
                        ->label('video')
                        ->maxSize(512000)
                        ->directory('lesson-videos')
                        ->default(null)
                        ->visibility('public')
                        ->acceptedFileTypes(['video/mp4', 'video/mov', 'video/avi', 'video/wmv', 'video/mp3', 'video/m4a', 'video/wma']),
    
                ])
                ->columns(3)
                ->defaultItems(0)
                ->addActionLabel('New')
                ->collapsible()
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? 'Video')
                ->columnSpanFull(),



            
            Textarea::make('description')
                ->columnSpanFull(),
        ]);
    }
}
