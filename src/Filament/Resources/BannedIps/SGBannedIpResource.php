<?php

namespace Cotiga\SpamGuard\Filament\Resources\BannedIps;

use BackedEnum;
use Cotiga\SpamGuard\Filament\Resources\BannedIps\Pages\ListSGBannedIps;
use Cotiga\SpamGuard\Models\BannedIp;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SGBannedIpResource extends Resource
{
    protected static ?string $model = BannedIp::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static ?string $navigationLabel = 'IP bannies';

    protected static ?string $modelLabel = 'IP bannie';

    protected static ?string $pluralModelLabel = 'IP bannies';

    protected static UnitEnum|string|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 10;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() || auth()->user()?->isManager();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('ip')
                    ->label('Adresse IP')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color('danger'),

                TextColumn::make('created_at')
                    ->label('Banni le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()->label('Débannir'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Débannir la sélection'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSGBannedIps::route('/'),
        ];
    }
}
