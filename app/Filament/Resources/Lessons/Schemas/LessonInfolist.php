<?php

namespace App\Filament\Resources\Lessons\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use App\Models\Course;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ViewEntry;
use Illuminate\Support\Facades\Storage;
class LessonInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('course_id')
                    ->numeric(),
                    TextEntry::make('course.name')
                    ->label('Course'),
                TextEntry::make('name'),
               
                ImageEntry::make('thumbnail')
                    ->disk('s3')
                    ->imageHeight(100),

                RepeatableEntry::make('video')
                    ->label('Videos')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Video Name')
                            ->columnSpan(1),
                        ImageEntry::make('thumbnail')
                            ->label('Thumbnail')
                            ->disk('s3')
                            ->imageHeight(80)
                            ->columnSpan(1),
                        ViewEntry::make('hls_playlist')
                            ->label('Video Player')
                            ->view('filament.components.hls-player')
                            ->columnSpanFull(),
        
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
