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
use Illuminate\Support\Facades\Log;
use App\Enums\WithdrawalReasonEnum;
use App\Filament\Admin\Resources\LinkSiteResource\RelationManagers\SellersRelationManager;
use App\Helpers\NumberFormatter;
use Carbon\Carbon;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class LinkSiteResource extends Resource
{
    protected static ?string $model = LinkSite::class;
    protected static ?string $navigationGroup = 'Links';
    protected static ?string $navigationIcon = 'fas-link';
    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('semrush_AS', '>', 0)
            ->where('is_withdrawn', 0)
            ->count();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Website')->schema([
                        TextInput::make('domain')->required()->unique(ignoreRecord: true),
                        TextInput::make('ip_address')->ipv4(),
                        Forms\Components\DatePicker::make('domain_creation_date'),
                        Forms\Components\DatePicker::make('last_checked'),
                    ])->columns(2),

                    Forms\Components\Section::make('SEMRush')->schema([
                        TextInput::make('semrush_AS')
                            ->label('Authority')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('semrush_traffic')
                            ->label('Traffic')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                        TextInput::make('semrush_perc_english_traffic')
                            ->label('% English')
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
                            ->visible(fn(Get $get): bool => $get('is_withdrawn'))
                            ->prohibitedUnless('is_withdrawn', 'true')
                            ->requiredIf('is_withdrawn', 'true')
                            ->options(WithdrawalReasonEnum::class)
                    ])->columns(2),
                ]),

                Forms\Components\Group::make()->schema([

                    Forms\Components\Section::make('Moz')->schema([
                        TextInput::make('moz_da')
                            ->label('DA')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('moz_pa')
                            ->label('PA')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('moz_rank')
                            ->label('Rank')
                            ->default(0.0)
                            ->rules(['numeric', 'between:0,10']),

                        TextInput::make('moz_links')
                            ->label('Links')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                    ])->columns(4),

                    Forms\Components\Section::make('Majestic')->schema([

                        TextInput::make('majestic_trust_flow')
                            ->label('TF')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('majestic_citation_flow')
                            ->label('CF')
                            ->default(0)
                            ->rules(['integer', 'between:0,100']),

                        TextInput::make('majestic_ref_domains')
                            ->label('RD')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                        TextInput::make('majestic_ref_edu')
                            ->label('.edu')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                        TextInput::make('majestic_ref_gov')
                            ->label('.gov')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                    ])->columns(5),

                    Forms\Components\Section::make('Other Metrics')->schema([

                        TextInput::make('facebook_shares')
                            ->label('FB Shares')
                            ->default(0)
                            ->rules(['integer', 'min:0']),

                    ])->columns(3),

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
                TextColumn::make('avg_low_price')->Label('Avg $')->sortable()
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('lowest_price')->Label('Low $')->sortable(),
                TextColumn::make('niches_count')->counts('niches')->Label('Niches')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_code')->Label('CO')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('semrush_AS')->label('SR AS')->sortable(),
                TextColumn::make('semrush_traffic')->label('Traffic')->sortable()->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('semrush_perc_english_traffic')->label('ENT %')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('semrush_organic_kw')->label('KW')->sortable()->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('moz_da')->label('DA'),
                TextColumn::make('moz_pa')->label('PA'),
                TextColumn::make('moz_rank')->label('MR'),
                TextColumn::make('moz_links')->label('Links')
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('domain_creation_date')->label('Age')->sortable()
                    ->formatStateUsing(function ($state)
                    {
                        $creationDate = Carbon::parse($state);
                        $now = Carbon::now();
                        $years = $creationDate->diffInYears($now);
                        $months = $creationDate->copy()->addYears($years)->diffInMonths($now);

                        return "{$years}y {$months}m";
                    }),
                TextColumn::make('majestic_trust_flow')->label('TF')->sortable(),
                TextColumn::make('majestic_citation_flow')->label('CF'),
                TextColumn::make('majestic_ref_domains')->label('RD')
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('majestic_ref_edu')->label('Maj .edu')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('majestic_ref_gov')->label('Maj .gov')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('facebook_shares')->label('FB')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->formatStateUsing(function ($state)
                    {
                        return NumberFormatter::format($state);
                    }),
                TextColumn::make('ahrefs_domain_rank')->label('DR')->sortable(),
                ToggleColumn::make('is_withdrawn')->label('W/D')->disabled()->toggleable(isToggledHiddenByDefault: true),
            ])

            ->defaultPaginationPageOption(10)

            ->defaultSort(
                fn($query) => $query
                    ->orderBy('is_withdrawn', 'asc')
                    ->orderBy('sellers_count', 'desc')
                    ->orderBy('avg_low_price', 'asc')
                    ->orderBy('majestic_trust_flow', 'desc')
                    ->orderBy('semrush_AS', 'desc')
            )


            ->filters([

                Tables\Filters\Filter::make('Live')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ;
                    }
                ),

                Tables\Filters\Filter::make('Purge')->query(
                    function ($query)
                    {
                        return $query
                            ->has('sellers', '=', 1)
                            ->where('semrush_as', '<', 2)

                            ;
                    }
                ),
                


                Tables\Filters\Filter::make('$25')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ->has('sellers', '>=', 4)
                            ->where('avg_low_price', '<=', 13)
                            ->where('lowest_price', '<=', 10)
                            ->where('moz_da', '>=', 15)
                            ->where('moz_pa', '>=', 10)
                            ->where('semrush_AS', '>=', 5)
                            ->where('majestic_trust_flow', '>=', 5)
                        ;
                    }
                ),
                
                Tables\Filters\Filter::make('$50')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ->has('sellers', '>=', 4)
                            ->where('avg_low_price', '<=', 35)
                            ->where('lowest_price', '<=', 25)
                            ->where('moz_da', '>=', 25)
                            ->where('moz_pa', '>=', 20)
                            ->where('semrush_AS', '>=', 10)
                            ->where('majestic_trust_flow', '>=', 10)
                        ;
                    }
                ),

                Tables\Filters\Filter::make('$100')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ->has('sellers', '>=', 4)
                            ->where('avg_low_price', '<=', 75)
                            ->where('lowest_price', '<=', 50)
                            ->where('moz_da', '>=', 35)
                            ->where('moz_pa', '>=', 30)
                            ->where('semrush_AS', '>=', 20)
                            ->where('majestic_trust_flow', '>=', 15)
                        ;
                    }
                ),

                Tables\Filters\Filter::make('$175')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ->has('sellers', '>=', 4)
                            ->where('avg_low_price', '<=', 125)
                            ->where('lowest_price', '<=', 85)
                            ->where('moz_da', '>=', 45)
                            ->where('moz_pa', '>=', 40)
                            ->where('semrush_AS', '>=', 25)
                            ->where('majestic_trust_flow', '>=', 20)
                        ;
                    }
                ),

                Tables\Filters\Filter::make('$275')->query(
                    function ($query)
                    {
                        return $query
                            ->where('is_withdrawn', 0)
                            ->has('sellers', '>=', 4)
                            ->where('avg_low_price', '<=', 200)
                            ->where('lowest_price', '<=', 135)
                            ->where('moz_da', '>=', 55)
                            ->where('moz_pa', '>=', 55)
                            ->where('semrush_AS', '>=', 30)
                            ->where('majestic_trust_flow', '>=', 25)
                        ;
                    }
                ),


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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
        ->withLowestPrice()    
        ->withAvgLowPrices()
            ;
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
