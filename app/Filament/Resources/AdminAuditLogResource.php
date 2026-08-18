<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdminAuditLogResource\Pages;
use App\Models\AdminAuditLog;
use Filament\Infolists;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class AdminAuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static ?string $modelLabel = 'Audit Log';

    protected static ?string $pluralModelLabel = 'Audit Logs';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform Security';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_email')
                    ->label('Admin Actor')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('action')
                    ->badge()
                    ->color(fn (string|null $state): string => match (true) {
                        str_contains($state ?? '', 'delete') || str_contains($state ?? '', 'deactivate') => 'danger',
                        str_contains($state ?? '', 'maintenance') || str_contains($state ?? '', 'password') => 'warning',
                        str_contains($state ?? '', 'create') || str_contains($state ?? '', 'activate') => 'success',
                        default => 'info',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(60),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Logged At')
                    ->dateTime('M d, Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->options([
                        'tenant.activated' => 'Tenant Activated',
                        'tenant.deactivated' => 'Tenant Deactivated',
                        'tenant.maintenance_enabled' => 'Maintenance Enabled',
                        'tenant.maintenance_disabled' => 'Maintenance Disabled',
                        'tenant.deleted' => 'Tenant Deleted',
                        'user.password_reset' => 'Password Reset',
                        'workflow.status_changed' => 'Workflow Status Changed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit Event Information')
                    ->components([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Log ID'),
                        Infolists\Components\TextEntry::make('action')
                            ->badge(),
                        Infolists\Components\TextEntry::make('user_email')
                            ->label('Super Admin Email')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('ip_address')
                            ->label('Client IP'),
                        Infolists\Components\TextEntry::make('target_type')
                            ->label('Target Model')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('target_id')
                            ->label('Target ID')
                            ->placeholder('N/A'),
                        Infolists\Components\TextEntry::make('description')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('properties')
                            ->label('Event Context / Changes')
                            ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : (string) $state)
                            ->visible(fn (AdminAuditLog $record): bool => ! empty($record->properties))
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Timestamp')
                            ->dateTime('F d, Y H:i:s'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminAuditLogs::route('/'),
            'view' => Pages\ViewAdminAuditLog::route('/{record}'),
        ];
    }
}
