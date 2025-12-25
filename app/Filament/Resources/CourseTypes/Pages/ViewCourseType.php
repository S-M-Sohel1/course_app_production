<?php

namespace App\Filament\Resources\CourseTypes\Pages;

use App\Filament\Resources\CourseTypes\CourseTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseType extends ViewRecord
{
    protected static string $resource = CourseTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
