<?php

namespace App\Filament\Resources\CourseTypes\Pages;

use App\Filament\Resources\CourseTypes\CourseTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCourseType extends CreateRecord
{
    protected static string $resource = CourseTypeResource::class;
}
