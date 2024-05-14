<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\LinkSiteResource\Pages;
use App\Filament\Admin\Resources\LinkSiteResource\RelationManagers;
use App\Models\LinkSite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Enums\WithdrawalReasonEnum;
use App\Filament\Admin\Resources\LinkSiteResource\RelationManagers\SellersRelationManager;
use App\Helpers\NumberFormatter;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class LinkSiteResource extends Resource
{
    protected static ?string $model = LinkSite::class;
    protected static ?string $navigationGroup = 'Links';
    protected static ?string $navigationIcon = 'fas-link';
    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('semrush_AS', '>=', 20)->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Website')->schema([
                        TextInput::make('domain')->required()->unique(ignoreRecord: true),
                        TextInput::make('ip_address')->ipv4(),
                        TextInput::make('domain_age')
                            ->label('Domain Age')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),
                        Forms\Components\DatePicker::make('last_checked'),
                    ])->columns(2),

                    Forms\Components\Section::make('SEMRush')->schema([
                        TextInput::make('semrush_AS')
                            ->label('Authority Score')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('semrush_traffic')
                            ->label('Traffic')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                        TextInput::make('semrush_perc_english_traffic')
                            ->label('% English Traffic')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('semrush_organic_kw')
                            ->label('Keywords')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                    ])->columns(4),

                    // TODO: Can't withdraw a site if there are any open orders for it
                    Forms\Components\Section::make('Remove site')->schema([
                        Forms\Components\Toggle::make('is_withdrawn')->label('Withdrawn')->reactive(),
                        Forms\Components\Select::make('withdrawn_reason')
                            ->visible(fn (Get $get): bool => $get('is_withdrawn'))
                            ->prohibitedUnless('is_withdrawn', 'true')
                            ->requiredIf('is_withdrawn', 'true')
                            ->options(WithdrawalReasonEnum::class)
                    ])->columns(2),
                ]),

                Forms\Components\Group::make()->schema([

                    Forms\Components\Section::make('Moz')->schema([
                        TextInput::make('moz_da')
                            ->label('Domain Authority')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('moz_pa')
                            ->label('Page Authority')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('moz_perc_quality_bl')
                            ->label('% Quality Links')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('moz_spam_score')
                            ->label('Spam Score')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                    ])->columns(4),

                    Forms\Components\Section::make('Majestic')->schema([

                        TextInput::make('majestic_trust_flow')
                            ->label('Trust Flow')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('majestic_citation_flow')
                            ->label('Citation Flow')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                    ])->columns(4),

                ]),

                Forms\Components\Section::make('Niches')->schema([
                    Forms\Components\CheckboxList::make('niches')->relationship('niches', 'name')->columns(3)
                ])->columns(1)->collapsible()->collapsed()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('domain')->sortable()->searchable(),
                TextColumn::make('sellers_count')->counts('sellers')->Label('Sellers')->sortable(),
                TextColumn::make('niches_count')->counts('niches')->Label('Niches')->sortable(),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_code')->Label('CO')->sortable(),
                TextColumn::make('semrush_AS')->label('SR AS')->sortable(),
                TextColumn::make('semrush_traffic')
                    ->numeric()
                    ->label('Traffic')
                    ->sortable()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('semrush_perc_english_traffic')->label('ENT %'),
                TextColumn::make('semrush_organic_kw')->label('KW')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('moz_da')->label('DA'),
                TextColumn::make('moz_pa')->label('PA'),
                TextColumn::make('moz_perc_quality_bl')->label('PQL %')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('moz_spam_score')->label('SS')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('domain_age')->label('Age')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('majestic_trust_flow')->label('TF')->sortable(),
                TextColumn::make('majestic_citation_flow')->label('CF'),
                TextColumn::make('ahrefs_domain_rank')->label('DR')->sortable(),
                ToggleColumn::make('is_withdrawn')->label('W/D')->disabled(),
            ])->defaultSort('sellers_count', 'desc')

            ->filters([
                //
            ])

            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make()
                ])
            ])

            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);

    }

    public static function getRelations(): array
    {
        return [
            SellersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLinkSites::route('/'),
            'create' => Pages\CreateLinkSite::route('/create'),
            'edit' => Pages\EditLinkSite::route('/{record}/edit'),
        ];
    }
}
