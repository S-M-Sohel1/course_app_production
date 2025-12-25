<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTeacherProfiles extends ListRecords
{
    protected static string $resource = TeacherProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
