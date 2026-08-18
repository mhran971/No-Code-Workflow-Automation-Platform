<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformAnnouncementResource\Pages;
use App\Models\PlatformAnnouncement;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformAnnouncementResource extends Resource
{
    protected static ?string $model = PlatformAnnouncement::class;

    protected static ?string $modelLabel = 'Platform Announcement';

    protected static ?string $pluralModelLabel = 'Platform Announcements';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Communications';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Announcement Details')
                    ->components([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->options([
                                'info' => 'Info (Blue)',
                                'warning' => 'Warning (Yellow)',
                                'danger' => 'Critical (Red)',
                                'success' => 'Success (Green)',
                            ])
                            ->default('info')
                            ->required(),
                        Forms\Components\Select::make('target_tenant_id')
                            ->label('Target Tenant')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty to broadcast to ALL tenants on the platform.'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Starts At')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->nullable(),
                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string|null $state): string => match ($state) {
                        'danger' => 'danger',
                        'warning' => 'warning',
                        'success' => 'success',
                        default => 'info',
                    }),
                Tables\Columns\TextColumn::make('tenant.business_name')
                    ->label('Target Audience')
                    ->placeholder('All Tenants (Global)')
                    ->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'primary'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Immediately')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Indefinite')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'info' => 'Info',
                        'warning' => 'Warning',
                        'danger' => 'Critical',
                        'success' => 'Success',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformAnnouncements::route('/'),
            'create' => Pages\CreatePlatformAnnouncement::route('/create'),
            'edit' => Pages\EditPlatformAnnouncement::route('/{record}/edit'),
        ];
    }
}
