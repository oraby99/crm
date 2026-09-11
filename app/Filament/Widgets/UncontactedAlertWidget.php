<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UncontactedAlertWidget extends BaseWidget
{
    protected static ?string $heading = 'تنبيه: بائعين لديهم عملاء لم يتم التواصل معهم';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->isAdmin() || Auth::user()?->isTeamLeader();
    }

    public function table(Table $table): Table
    {
        $user = Auth::user();

        $query = User::query()
            ->where('role', UserRole::Sales->value)
            ->where('is_active', true)
            ->withCount([
                'assignedCustomers as uncontacted_count' => function ($query) {
                    $query->whereDoesntHave('activities');
                }
            ])
            ->having('uncontacted_count', '>', 0)
            ->orderByDesc('uncontacted_count');

        if ($user && $user->isTeamLeader()) {
            $query->where('team_leader_id', $user->id);
        }

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('المندوب')
                    ->searchable(),

                Tables\Columns\TextColumn::make('uncontacted_count')
                    ->label('عدد العملاء المتأخرين (لم يتم التواصل)')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
            ])
            ->actions([
                \Filament\Actions\Action::make('view_customers')
                    ->label('عرض العملاء')
                    ->icon('heroicon-o-users')
                    ->url(fn (User $record) => route('filament.admin.resources.customers.index', [
                        'alert_not_contacted' => 1,
                        'alert_sales_id' => $record->id,
                    ])),
            ])
            ->emptyStateHeading('ممتاز! جميع المندوبين قاموا بالتواصل مع عملائهم')
            ->emptyStateDescription('لا يوجد حالياً أي بائع لديه عملاء معلقين لم يتم التواصل معهم.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
