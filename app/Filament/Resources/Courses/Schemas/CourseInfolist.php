<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;

use Filament\Infolists\Components\FileEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\VideoEntry;
use Illuminate\Contracts\Cache\Store;
use Storage;

class CourseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name'),
                
                TextEntry::make('teacher.name')
                    ->label('Teacher'),

                
                
                ImageEntry::make('thumbnail')
                ->disk('s3')
                ->visibility('public')
                
                ->imageHeight(100),

                        TextEntry::make('video')
                            ->html()
                            ->formatStateUsing(fn($state) => $state
                                ? "<video width='320' height='200' controls>
                                       <source src='" . Storage::disk('s3')->url($state) . "' type='video/mp4'>
                                       Your browser does not support the video tag.
                                   </video>"
                                : 'No video available'),



                TextEntry::make('type_id'),
                TextEntry::make('price'),
                TextEntry::make('lesson_num'),
                TextEntry::make('video_length'),
                TextEntry::make('follow'),
                TextEntry::make('score'),
                TextEntry::make('created_at'),
            ]);
    }
}
