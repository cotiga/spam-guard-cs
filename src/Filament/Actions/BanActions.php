<?php

namespace Cotiga\SpamGuard\Filament\Actions;

use Cotiga\SpamGuard\Models\BannedEmail;
use Cotiga\SpamGuard\Models\BannedIp;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Actions « Bannir IP » / « Bannir e-mail » à poser sur n'importe quelle liste de
 * messages reçus (contacts, réservations, demandes, témoignages…). Chaque table a
 * ses propres noms de colonnes : contacts et demandes utilisent 'mel', resaform
 * 'email', les témoignages n'ont pas d'adresse du tout.
 *
 * Usage dans un recordActions() :
 *
 *   ...BanActions::make('mel'),          // IP + e-mail
 *   ...BanActions::make(null),           // IP seule
 *
 * Un bouton disparaît dès que l'IP ou l'adresse est bannie — l'écran « IP bannies »
 * reste le seul endroit pour débannir.
 */
class BanActions
{
    /**
     * @return array<int, Action>
     */
    public static function make(?string $emailField = 'mel', string $ipField = 'ip'): array
    {
        $actions = [
            Action::make('banIp')
                ->label('Bannir IP')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(fn ($record) => 'L\'adresse '.$record->{$ipField}.' ne pourra plus accéder au site ni envoyer de formulaire.')
                ->visible(fn ($record) => filled($record->{$ipField}) && ! BannedIp::where('ip', $record->{$ipField})->exists())
                ->action(fn ($record) => BannedIp::firstOrCreate(['ip' => $record->{$ipField}])),
        ];

        if ($emailField !== null) {
            $actions[] = Action::make('banEmail')
                ->label('Bannir e-mail')
                ->icon(Heroicon::OutlinedEnvelopeOpen)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(fn ($record) => 'L\'adresse '.$record->{$emailField}.' ne pourra plus envoyer de formulaire.')
                ->visible(fn ($record) => filled($record->{$emailField}) && ! BannedEmail::where('mel', static::normalise($record->{$emailField}))->exists())
                ->action(fn ($record) => BannedEmail::firstOrCreate(['mel' => static::normalise($record->{$emailField})]));
        }

        return $actions;
    }

    /**
     * Les adresses sont stockées et comparées en minuscules, sans espaces —
     * même normalisation que FormSpamGuard.
     */
    private static function normalise(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
