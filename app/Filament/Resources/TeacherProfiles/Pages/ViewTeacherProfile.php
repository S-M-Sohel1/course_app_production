<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTeacherProfile extends ViewRecord
{
    protected static string $resource = TeacherProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
