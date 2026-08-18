<?php

namespace App\Filament\Resources\PlatformAnnouncementResource\Pages;

use App\Filament\Resources\PlatformAnnouncementResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePlatformAnnouncement extends CreateRecord
{
    protected static string $resource = PlatformAnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = auth()->id();

        return $data;
    }
}
