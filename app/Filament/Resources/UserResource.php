<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use Filament\Actions;
use Filament\Forms;
use Filament\Infolists;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\User;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'User & Access';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account')
                    ->components([
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->relationship('tenant', 'business_name')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Leave empty for Platform Super Admins.'),
                        Forms\Components\Select::make('role')
                            ->options([
                                Role::SuperAdmin->value => 'Super Admin (Platform)',
                                Role::BusinessOwner->value => 'Business Owner',
                                Role::Manager->value => 'Manager',
                                Role::Admin->value => 'Admin',
                                Role::Employee->value => 'Employee',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('first_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('last_name')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->label('Display Name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('position')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Status')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.business_name')
                    ->label('Tenant')
                    ->placeholder('Platform Admin')
                    ->badge()
                    ->color(fn ($state) => $state ? 'info' : 'danger')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (Role|string|null $state): string => match ($state instanceof Role ? $state->value : (string) $state) {
                        Role::SuperAdmin->value => 'danger',
                        Role::BusinessOwner->value => 'warning',
                        Role::Manager->value => 'info',
                        Role::Admin->value => 'primary',
                        Role::Employee->value => 'gray',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('position')
                    ->placeholder('N/A')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        Role::SuperAdmin->value => 'Super Admin',
                        Role::BusinessOwner->value => 'Business Owner',
                        Role::Manager->value => 'Manager',
                        Role::Admin->value => 'Admin',
                        Role::Employee->value => 'Employee',
                    ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'business_name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->minLength(8),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->password = Hash::make($data['new_password']);
                        $record->save();

                        \App\Models\AdminAuditLog::record(
                            'user.password_reset',
                            "Password was reset for user '{$record->email}'",
                            $record,
                            ['user_id' => $record->id, 'email' => $record->email]
                        );

                        Notification::make()
                            ->title('Password reset successfully')
                            ->body("Password for {$record->email} has been updated.")
                            ->success()
                            ->send();
                    }),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Details')
                    ->components([
                        Infolists\Components\TextEntry::make('name')
                            ->weight('bold'),
                        Infolists\Components\TextEntry::make('email'),
                        Infolists\Components\TextEntry::make('tenant.business_name')
                            ->label('Tenant')
                            ->placeholder('Platform Super Admin')
                            ->badge(),
                        Infolists\Components\TextEntry::make('role')
                            ->badge(),
                        Infolists\Components\TextEntry::make('position')
                            ->placeholder('N/A'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Active')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime('F d, Y H:i:s'),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
