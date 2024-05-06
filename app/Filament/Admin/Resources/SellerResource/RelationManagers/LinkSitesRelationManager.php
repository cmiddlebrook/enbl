<?php

namespace App\Filament\Admin\Resources\SellerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LinkSitesRelationManager extends RelationManager
{
    protected static string $relationship = 'linkSites';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain'),
                Tables\Columns\TextColumn::make('pivot.price_guest_post')->label('GP Price'),
                Tables\Columns\TextColumn::make('pivot.price_link_insertion')->label('LI Price'),
            ])->defaultSort('domain')
            ->filters([
                //
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
