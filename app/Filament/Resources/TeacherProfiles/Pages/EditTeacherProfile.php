<?php

namespace App\Filament\Resources\TeacherProfiles\Pages;

use App\Filament\Resources\TeacherProfiles\TeacherProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTeacherProfile extends EditRecord
{
    protected static string $resource = TeacherProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
