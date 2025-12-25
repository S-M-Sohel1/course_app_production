<?php

namespace App\Filament\Resources\Courses\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
class CoursesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                ImageColumn::make('thumbnail')
                ->square()
                ->disk('public'),
               
                TextColumn::make('courseType.title'),
                TextColumn::make('teacher.name')
                    ->label('Teacher'),
                TextColumn::make('price'),
                IconColumn::make('recommended')
                ->boolean(),
             
               
                TextColumn::make('follow'),
                TextColumn::make('score'),
               // TextColumn::make('created_at'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
