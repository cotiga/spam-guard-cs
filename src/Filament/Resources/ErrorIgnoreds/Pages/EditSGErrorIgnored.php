<?php

namespace Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\Pages;

use Cotiga\SpamGuard\Filament\Resources\ErrorIgnoreds\SGErrorIgnoredResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSGErrorIgnored extends EditRecord
{
    protected static string $resource = SGErrorIgnoredResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
