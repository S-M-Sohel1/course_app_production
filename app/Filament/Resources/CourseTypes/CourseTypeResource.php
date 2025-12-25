<?php

namespace App\Filament\Resources\CourseTypes;

use App\Filament\Resources\CourseTypes\Pages\CreateCourseType;
use App\Filament\Resources\CourseTypes\Pages\EditCourseType;
use App\Filament\Resources\CourseTypes\Pages\ListCourseTypes;
use App\Filament\Resources\CourseTypes\Pages\ViewCourseType;
use App\Filament\Resources\CourseTypes\Schemas\CourseTypeForm;
use App\Filament\Resources\CourseTypes\Schemas\CourseTypeInfolist;
use App\Filament\Resources\CourseTypes\Tables\CourseTypesTable;
use App\Models\CourseType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CourseTypeResource extends Resource
{
    protected static ?string $model = CourseType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'coursetype';

    public static function form(Schema $schema): Schema
    {
        return CourseTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CourseTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CourseTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourseTypes::route('/'),
            'create' => CreateCourseType::route('/create'),
            'view' => ViewCourseType::route('/{record}'),
            'edit' => EditCourseType::route('/{record}/edit'),
        ];
    }
}
