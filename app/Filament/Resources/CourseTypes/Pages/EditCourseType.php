<?php

namespace App\Filament\Resources\CourseTypes\Pages;

use App\Filament\Resources\CourseTypes\CourseTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCourseType extends EditRecord
{
    protected static string $resource = CourseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
