<?php

namespace App\Filament\Admin\Resources\LinkSiteResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SellersRelationManager extends RelationManager
{
    protected static string $relationship = 'sellers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(
                fn (Model $record): string => route('filament.admin.resources.sellers.edit', ['record' => $record]),
            )
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\TextColumn::make('pivot.price_guest_post')->label('GP Price'),
                Tables\Columns\TextColumn::make('pivot.price_link_insertion')->label('LI Price'),
            ])->defaultSort('price_guest_post', 'asc')
            ->filters([
                //
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
