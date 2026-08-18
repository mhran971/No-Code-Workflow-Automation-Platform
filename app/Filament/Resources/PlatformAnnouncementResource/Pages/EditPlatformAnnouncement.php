<?php

namespace App\Filament\Resources\PlatformAnnouncementResource\Pages;

use App\Filament\Resources\PlatformAnnouncementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlatformAnnouncement extends EditRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
