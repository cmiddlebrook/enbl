<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\SellerResource\Pages;
use App\Filament\Admin\Resources\SellerResource\RelationManagers;
use App\Filament\Admin\Resources\SellerResource\RelationManagers\LinkSitesRelationManager;
use App\Models\Seller;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Helpers\NumberFormatter;

class SellerResource extends Resource
{
    protected static ?string $model = Seller::class;
    protected static ?string $navigationGroup = 'Resources';
    protected static ?string $navigationIcon = 'fas-user-secret';
    protected static ?int $navigationSort = 1;


    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::getModel()::count() < 10 ? 'danger' : 'primary';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make()->schema([
                        TextInput::make('name'),
                        TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                        Forms\Components\MarkdownEditor::make('notes')
                    ])
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('last_import')->date()->sortable(),
                TextColumn::make('linksites_count')->counts('linksites')->Label('Nr Sites')->sortable(),
                TextColumn::make('num_withdrawn_sites')->Label('Nr WD'),
                ToggleColumn::make('is_blocked')->label('Blocked')->disabled()->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('last_import', 'asc')

            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),

                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            LinkSitesRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSellers::route('/'),
            'create' => Pages\CreateSeller::route('/create'),
            'edit' => Pages\EditSeller::route('/{record}/edit'),
        ];
    }

    protected static function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->withCount('linkSites');
    }
}
