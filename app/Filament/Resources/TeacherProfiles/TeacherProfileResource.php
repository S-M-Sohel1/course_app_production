<?php

namespace App\Filament\Resources\TeacherProfiles;

use App\Filament\Resources\TeacherProfiles\Pages\CreateTeacherProfile;
use App\Filament\Resources\TeacherProfiles\Pages\EditTeacherProfile;
use App\Filament\Resources\TeacherProfiles\Pages\ListTeacherProfiles;
use App\Filament\Resources\TeacherProfiles\Pages\ViewTeacherProfile;
use App\Filament\Resources\TeacherProfiles\Schemas\TeacherProfileForm;
use App\Filament\Resources\TeacherProfiles\Schemas\TeacherProfileInfolist;
use App\Filament\Resources\TeacherProfiles\Tables\TeacherProfilesTable;
use App\Models\TeacherProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TeacherProfileResource extends Resource
{
    protected static ?string $model = TeacherProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'teacher';

    public static function form(Schema $schema): Schema
    {
        return TeacherProfileForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TeacherProfileInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TeacherProfilesTable::configure($table);
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
            'index' => ListTeacherProfiles::route('/'),
            'create' => CreateTeacherProfile::route('/create'),
            'view' => ViewTeacherProfile::route('/{record}'),
            'edit' => EditTeacherProfile::route('/{record}/edit'),
        ];
    }
}
