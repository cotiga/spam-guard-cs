<?php

namespace Cotiga\SpamGuard\Filament\Resources\RefusedContacts;

use BackedEnum;
use Cotiga\SpamGuard\Filament\Resources\RefusedContacts\Pages\ListSGRefusedContacts;
use Cotiga\SpamGuard\Models\BannedIp;
use Cotiga\SpamGuard\Models\RefusedContact;
use Filament\Actions\Action;
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

class SGRefusedContactResource extends Resource
{
    protected static ?string $model = RefusedContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Refus antispam';

    protected static ?string $modelLabel = 'Refus';

    protected static ?string $pluralModelLabel = 'Refus antispam';

    protected static UnitEnum|string|null $navigationGroup = 'Sécurité';

    protected static ?int $navigationSort = 9;

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
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('form_name')
                    ->label('Formulaire')
                    ->badge()
                    ->sortable(),

                TextColumn::make('mel')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('pays')
                    ->label('Pays')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('ip')
                    ->label('IP')
                    ->searchable()
                    ->copyable()
                    ->badge()
                    ->color(fn ($record) => BannedIp::where('ip', $record->ip)->exists() ? 'danger' : 'gray'),

                TextColumn::make('raison')
                    ->label('Raison du refus')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('form_name')
                    ->label('Formulaire')
                    ->options(fn () => RefusedContact::distinct()->pluck('form_name', 'form_name')->toArray()),
            ])
            ->recordActions([
                Action::make('ban')
                    ->label('Bannir IP')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->ip && ! BannedIp::where('ip', $record->ip)->exists())
                    ->action(fn ($record) => BannedIp::firstOrCreate(['ip' => $record->ip])),
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
            'index' => ListSGRefusedContacts::route('/'),
        ];
    }
}
