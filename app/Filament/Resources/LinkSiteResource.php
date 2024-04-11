<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LinkSiteResource\Pages;
use App\Filament\Resources\LinkSiteResource\RelationManagers;
use App\Models\LinkSite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LinkSiteResource extends Resource
{
    protected static ?string $model = LinkSite::class;
    protected static ?string $navigationGroup = 'Links';
    protected static ?string $navigationIcon = 'fas-link';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Website')->schema([
                        Forms\Components\TextInput::make('domain')->required(),
                        Forms\Components\TextInput::make('ip_address')->ipv4(),
                        Forms\Components\TextInput::make('domain_age')
                            ->label('Domain Age')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),
                        Forms\Components\DatePicker::make('last_checked'),
                    ])->columns(2)
                ]),

                Forms\Components\Group::make()->schema([
                    // TODO: Can't withdraw a site if there are any open orders for it
                    Forms\Components\Section::make('Remove site')->schema([
                        Forms\Components\Toggle::make('is_withdrawn')->label('Withdrawn'),
                        Forms\Components\Select::make('withdrawn_reason')
                            ->requiredIf('is_withdrawn', 'true')
                            ->options([
                                'spam' => 'High Spam Score',
                                'dupe_ip' => 'Duplicate IP Address',
                                'non_en' => 'Not English' // TODO: Set these up as an enum?
                            ])
                    ])->columns(2)
                ]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('SEMRush')->schema([
                        Forms\Components\TextInput::make('semrush_AS')
                            ->label('Authority Score')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        // TODO: Format the big numbers better. Lary had ideas about this - I need a callback
                        Forms\Components\TextInput::make('semrush_traffic')
                            ->label('Traffic')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->rules(['integer', 'min:0']),

                        Forms\Components\TextInput::make('semrush_perc_english_traffic')
                            ->label('% English Traffic')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        Forms\Components\TextInput::make('semrush_organic_kw')
                            ->label('Keywords')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->rules(['integer', 'min:0']),

                    ])->columns(4)
                ]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Moz')->schema([
                        Forms\Components\TextInput::make('moz_da')
                            ->label('Domain Authority')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        Forms\Components\TextInput::make('moz_pa')
                            ->label('Page Authority')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        Forms\Components\TextInput::make('moz_perc_quality_bl')
                            ->label('% Quality Links')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        Forms\Components\TextInput::make('moz_spam_score')
                            ->label('Spam Score')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                    ])->columns(4)
                ]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Majestic')->schema([

                        Forms\Components\TextInput::make('majestic_trust_flow')
                            ->label('Trust Flow')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                        Forms\Components\TextInput::make('majestic_citation_flow')
                            ->label('Citation Flow')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                    ])->columns(4)
                ]),

                Forms\Components\Group::make()->schema([
                    Forms\Components\Section::make('Ahrefs')->schema([

                        Forms\Components\TextInput::make('ahrefs_domain_rank')
                            ->label('Domain Rank')
                            ->numeric()
                            ->integer()
                            ->default(0)
                            ->minValue(0)
                            ->maxValue(100)
                            ->rules(['integer', 'between:0,100']),

                    ])->columns(4)
                ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')->sortable(),
                Tables\Columns\TextColumn::make('ip_address'),
                Tables\Columns\TextColumn::make('semrush_AS')->label('SR AS')->sortable(),
                Tables\Columns\TextColumn::make('semrush_traffic')->label('Traffic')->sortable(),
                Tables\Columns\TextColumn::make('semrush_perc_english_traffic')->label('ENT %'),
                Tables\Columns\TextColumn::make('semrush_organic_kw')->label('KW')->sortable(),
                Tables\Columns\TextColumn::make('moz_da')->label('DA')->sortable(),
                Tables\Columns\TextColumn::make('moz_pa')->label('PA'),
                Tables\Columns\TextColumn::make('moz_perc_quality_bl')->label('PQL %'),
                Tables\Columns\TextColumn::make('moz_spam_score')->label('SS'),
                Tables\Columns\TextColumn::make('domain_age')->label('Age'),
                Tables\Columns\TextColumn::make('majestic_trust_flow')->label('TF')->sortable(),
                Tables\Columns\TextColumn::make('majestic_citation_flow')->label('CF'),
                Tables\Columns\TextColumn::make('ahrefs_domain_rank')->label('DR')->sortable(),
                Tables\Columns\ToggleColumn::make('is_withdrawn')->label('W/D')->disabled(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListLinkSites::route('/'),
            'create' => Pages\CreateLinkSite::route('/create'),
            'edit' => Pages\EditLinkSite::route('/{record}/edit'),
        ];
    }
}
