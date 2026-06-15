<?php

namespace Cotiga\SpamGuard\Filament\Resources\BannedEmails;

use BackedEnum;
use Cotiga\SpamGuard\Filament\Resources\BannedEmails\Pages\ListSGBannedEmails;
use Cotiga\SpamGuard\Models\BannedEmail;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SGBannedEmailResource extends Resource
{
    protected static ?string $model = BannedEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static ?string $navigationLabel = 'E-mails bannis';

    protected static ?string $modelLabel = 'E-mail banni';

    protected static ?string $pluralModelLabel = 'E-mails bannis';

    protected static UnitEnum|string|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 11;

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
                TextColumn::make('mel')
                    ->label('Adresse e-mail')
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
            'index' => ListSGBannedEmails::route('/'),
        ];
    }
}
