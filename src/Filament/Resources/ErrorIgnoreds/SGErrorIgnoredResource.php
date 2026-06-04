<?php

namespace Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds;

use BackedEnum;
use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages\CreateSGErrorIgnored;
use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages\EditSGErrorIgnored;
use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages\ListSGErrorIgnoreds;
use Cotiga\SpamGuard\Models\ErrorIgnored;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SGErrorIgnoredResource extends Resource
{
    protected static ?string $model = ErrorIgnored::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEyeSlash;

    protected static ?string $navigationLabel = 'URLs ignorées';

    protected static ?string $modelLabel = 'URL ignorée';

    protected static ?string $pluralModelLabel = 'URLs ignorées';

    protected static UnitEnum|string|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 12;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('pattern')
                ->label('Motif d\'URL')
                ->required()
                ->maxLength(255)
                ->helperText('Chemin sans le domaine. Le caractère * sert de joker. Ex. : wp-login.php ou admin/*')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('pattern')
            ->columns([
                TextColumn::make('pattern')
                    ->label('Motif d\'URL')
                    ->badge()
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSGErrorIgnoreds::route('/'),
            'create' => CreateSGErrorIgnored::route('/create'),
            'edit' => EditSGErrorIgnored::route('/{record}/edit'),
        ];
    }
}
