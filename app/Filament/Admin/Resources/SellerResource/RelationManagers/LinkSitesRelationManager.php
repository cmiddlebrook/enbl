<?php

namespace App\Filament\Admin\Resources\SellerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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
            ->recordUrl(
                fn (Model $record): string => route('filament.admin.resources.link-sites.edit', ['record' => $record]),
            )
            ->recordTitleAttribute('domain')
            ->columns([
                Tables\Columns\TextColumn::make('domain'),
                Tables\Columns\ToggleColumn::make('is_withdrawn')->label('W/D')->disabled(),
                Tables\Columns\TextColumn::make('pivot.price_guest_post')->label('GP Price'),
                Tables\Columns\TextColumn::make('semrush_AS')->label('SR AS')->sortable(),
                Tables\Columns\TextColumn::make('majestic_trust_flow')->label('TF')->sortable(),
                Tables\Columns\TextColumn::make('majestic_citation_flow')->label('CF'),
                Tables\Columns\TextColumn::make('moz_da')->label('DA'),
            ])
            
            ->defaultSort(
                fn($query) => $query
                    ->orderBy('is_withdrawn', 'asc')
                    ->orderBy('price_guest_post', 'asc')
                    ->orderBy('semrush_AS', 'desc')
                    ->orderBy('majestic_trust_flow', 'desc')
            )

            ->filters([
                //
            ]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
