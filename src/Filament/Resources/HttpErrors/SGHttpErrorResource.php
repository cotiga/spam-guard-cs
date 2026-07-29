<?php

namespace Cotiga\SpamGuard\Filament\Resources\HttpErrors;

use BackedEnum;
use Cotiga\SpamGuard\Filament\Resources\HttpErrors\Pages\ListSGHttpErrors;
use Cotiga\SpamGuard\Filament\Actions\BanActions;
use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\HttpError;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SGHttpErrorResource extends Resource
{
    protected static ?string $model = HttpError::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Erreurs HTTP';

    protected static ?string $modelLabel = 'Erreur HTTP';

    protected static ?string $pluralModelLabel = 'Erreurs HTTP';

    protected static UnitEnum|string|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 11;

    public static function canViewAny(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
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
            ->defaultSort('count', 'desc')
            ->columns([
                TextColumn::make('status_code')
                    ->label('Code')
                    ->badge()
                    ->color(fn ($state) => $state >= 500 ? 'danger' : ($state === 404 ? 'warning' : 'gray'))
                    ->sortable(),

                TextColumn::make('count')
                    ->label('Occurrences')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('url')
                    ->label('URL')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->url)
                    ->searchable()
                    ->wrap(),

                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color(fn ($record) => BannedIp::where('ip', $record->ip)->exists() ? 'danger' : 'gray'),

                TextColumn::make('error_message')
                    ->label('Message')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Dernière vue')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status_code')
                    ->label('Code HTTP')
                    ->options(fn () => HttpError::distinct()->pluck('status_code', 'status_code')->toArray()),
            ])
            ->recordActions([
                // Une erreur HTTP n'a pas d'adresse e-mail : IP seule.
                ...BanActions::make(null),
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
            'index' => ListSGHttpErrors::route('/'),
        ];
    }
}
