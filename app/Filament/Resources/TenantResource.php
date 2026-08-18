<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Filament\Resources\TenantResource\RelationManagers;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Modules\Auth\Enums\BusinessType;
use Modules\Auth\Models\Tenant;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string|\UnitEnum|null $navigationGroup = 'Tenant Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tenant Details')
                    ->components([
                        Forms\Components\TextInput::make('business_name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('business_type')
                            ->options(BusinessType::labels())
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                        Forms\Components\Toggle::make('maintenance_mode')
                            ->label('Maintenance Mode')
                            ->default(false),
                        Forms\Components\Textarea::make('maintenance_message')
                            ->label('Maintenance Notice')
                            ->rows(3)
                            ->placeholder('Notice displayed to tenant users when maintenance mode is enabled.')
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
                Tables\Columns\TextColumn::make('business_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('business_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof BusinessType ? BusinessType::labels()[$state->value] ?? $state->value : (BusinessType::labels()[$state] ?? $state))
                    ->color('info')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\IconColumn::make('maintenance_mode')
                    ->label('Maintenance')
                    ->boolean()
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Users')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('teams_count')
                    ->counts('teams')
                    ->label('Teams')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workflows_count')
                    ->counts('workflows')
                    ->label('Workflows')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('business_type')
                    ->options(BusinessType::labels()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
                Tables\Filters\TernaryFilter::make('maintenance_mode')
                    ->label('Maintenance Mode'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggleActive')
                    ->label(fn (Tenant $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (Tenant $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn (Tenant $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Tenant $record): string => ($record->is_active ? 'Deactivate ' : 'Activate ') . $record->business_name)
                    ->modalDescription(fn (Tenant $record): string => $record->is_active
                        ? 'Deactivating will prevent all users of this tenant from accessing the platform.'
                        : 'Activating will restore full platform access for this tenant and its users.')
                    ->action(function (Tenant $record): void {
                        $record->is_active = ! $record->is_active;
                        $record->deactivated_at = $record->is_active ? null : now();
                        $record->save();

                        Notification::make()
                            ->title('Tenant status updated')
                            ->body("{$record->business_name} is now " . ($record->is_active ? 'Active' : 'Deactivated'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('toggleMaintenance')
                    ->label(fn (Tenant $record): string => $record->maintenance_mode ? 'Disable Maintenance' : 'Enable Maintenance')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->color('warning')
                    ->form([
                        Forms\Components\Textarea::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->default(fn (Tenant $record) => $record->maintenance_message)
                            ->placeholder('e.g. System upgrade in progress. Workflow execution is temporarily paused.')
                            ->rows(3),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $record->maintenance_mode = ! $record->maintenance_mode;
                        $record->maintenance_message = $record->maintenance_mode ? ($data['maintenance_message'] ?? null) : null;
                        $record->save();

                        Notification::make()
                            ->title('Maintenance mode updated')
                            ->body("{$record->business_name} maintenance mode is now " . ($record->maintenance_mode ? 'Enabled' : 'Disabled'))
                            ->warning()
                            ->send();
                    }),
                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-finger-print')
                    ->color('info')
                    ->modalHeading(fn (Tenant $record): string => "Impersonate {$record->business_name} Owner")
                    ->modalDescription('Generate an immediate JWT session for the owner of this tenant.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->form(function (Tenant $record) {
                        $owner = $record->users()->where('role', \Modules\Auth\Enums\Role::BusinessOwner)->first()
                            ?? $record->users()->first();

                        if (! $owner) {
                            return [
                                Forms\Components\Placeholder::make('no_owner')
                                    ->label('Notice')
                                    ->content('No users found for this tenant.'),
                            ];
                        }

                        $token = auth('api')->login($owner);

                        return [
                            Forms\Components\TextInput::make('impersonated_user')
                                ->label('User Account')
                                ->default("{$owner->name} ({$owner->email})")
                                ->disabled(),
                            Forms\Components\TextInput::make('impersonated_role')
                                ->label('Role')
                                ->default($owner->role instanceof \Modules\Auth\Enums\Role ? $owner->role->value : $owner->role)
                                ->disabled(),
                            Forms\Components\Textarea::make('jwt_token')
                                ->label('Bearer JWT Token')
                                ->default($token)
                                ->rows(4)
                                ->disabled()
                                ->helperText('Use this JWT token to test and troubleshoot API endpoints as this tenant owner.'),
                        ];
                    }),
                Tables\Actions\DeleteAction::make()
                    ->modalHeading(fn (Tenant $record): string => "Delete {$record->business_name}")
                    ->modalDescription('Are you sure you want to delete this tenant? All associated users, teams, workflows, and executions will be permanently removed.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tenant Overview')
                    ->components([
                        Infolists\Components\TextEntry::make('business_name')
                            ->label('Business Name')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('business_type')
                            ->label('Business Type')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state instanceof BusinessType ? BusinessType::labels()[$state->value] ?? $state->value : (BusinessType::labels()[$state] ?? $state)),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active Status')
                            ->boolean(),
                        Infolists\Components\IconEntry::make('maintenance_mode')
                            ->label('Maintenance Mode')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('maintenance_message')
                            ->label('Maintenance Message')
                            ->placeholder('None')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registered At')
                            ->dateTime('F d, Y H:i:s'),
                        Infolists\Components\TextEntry::make('deactivated_at')
                            ->label('Deactivated At')
                            ->dateTime('F d, Y H:i:s')
                            ->placeholder('N/A'),
                    ])->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsersRelationManager::class,
            RelationManagers\TeamsRelationManager::class,
            RelationManagers\WorkflowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
