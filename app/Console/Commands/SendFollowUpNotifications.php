<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendFollowUpNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-follow-up-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'فحص وإرسال إشعارات النظام المجدولة للمتابعات اليومية والمتأخرة والعملاء الجدد المعلقين';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('جاري فحص المتابعات وإرسال الإشعارات...');

        $allUsers = User::where('is_active', true)->get();
        $admins = $allUsers->filter(fn (User $u) => $u->isAdmin());
        $teamLeaders = $allUsers->filter(fn (User $u) => $u->isTeamLeader());
        $salesUsers = $allUsers->filter(fn (User $u) => $u->isSales());

        $pendingQuery = function ($query) {
            $query->where(function ($q) {
                $q->where('next_follow_up_at', '<=', now())
                    ->orWhereNull('next_follow_up_at')
                    ->orWhereDoesntHave('activities');
            });
        };

        $totalPending = Customer::withoutGlobalScope(\App\Scopes\CustomerScope::class)->where($pendingQuery)->count();

        $notificationsSent = 0;

        if ($totalPending > 0) {
            foreach ($admins as $admin) {
                $notif = Notification::make()
                    ->title('تنبيه الإدارة: متابعات وعملاء معلقين')
                    ->body("يوجد إجمالاً {$totalPending} عملاء بحاجة للمتابعة أو التواصل الفوري في النظام.")
                    ->icon('heroicon-o-exclamation-triangle')
                    ->danger()
                    ->actions([
                        Action::make('view')
                            ->label('مراجعة كافة العملاء')
                            ->url(route('filament.admin.resources.customers.index')),
                    ]);

                $this->saveDirectNotification($admin, $notif);
                $notificationsSent++;
            }

            foreach ($salesUsers as $sales) {
                $count = Customer::withoutGlobalScope(\App\Scopes\CustomerScope::class)
                    ->where('sales_id', $sales->id)
                    ->where($pendingQuery)
                    ->count();

                if ($count > 0) {
                    $notif = Notification::make()
                        ->title('تنبيه عاجل: متابعات تحتاج لتواصلك')
                        ->body("لديك {$count} عملاء يحتاجون للتواصل والمتابعة الفورية.")
                        ->icon('heroicon-o-clock')
                        ->warning()
                        ->actions([
                            Action::make('view')
                                ->label('عرض العملاء المعلقين')
                                ->url(route('filament.admin.resources.customers.index')),
                        ]);

                    $this->saveDirectNotification($sales, $notif);
                    $notificationsSent++;
                }
            }

            foreach ($teamLeaders as $tl) {
                $count = Customer::withoutGlobalScope(\App\Scopes\CustomerScope::class)
                    ->where('team_leader_id', $tl->id)
                    ->where($pendingQuery)
                    ->count();

                if ($count > 0) {
                    $notif = Notification::make()
                        ->title('تنبيه مدير الفريق: متابعات معلقة بالفريق')
                        ->body("أعضاء فريقك لديهم {$count} عملاء يحتاجون للمتابعة والتواصل.")
                        ->icon('heroicon-o-exclamation-circle')
                        ->warning()
                        ->actions([
                            Action::make('view')
                                ->label('عرض عملاء الفريق')
                                ->url(route('filament.admin.resources.customers.index')),
                        ]);

                    $this->saveDirectNotification($tl, $notif);
                    $notificationsSent++;
                }
            }
        }

        $totalToday = Customer::withoutGlobalScope(\App\Scopes\CustomerScope::class)
            ->whereDate('next_follow_up_at', today())
            ->count();

        if ($totalToday > 0) {
            foreach ($admins as $admin) {
                $notif = Notification::make()
                    ->title('تنبيه الإدارة: متابعات اليوم')
                    ->body("يوجد إجمالاً {$totalToday} متابعات مجدولة لليوم في النظام.")
                    ->icon('heroicon-o-calendar')
                    ->info()
                    ->actions([
                        Action::make('view')
                            ->label('عرض المتابعات اليومية')
                            ->url(route('filament.admin.resources.customers.index')),
                    ]);

                $this->saveDirectNotification($admin, $notif);
                $notificationsSent++;
            }

            foreach ($salesUsers as $sales) {
                $todayCount = Customer::withoutGlobalScope(\App\Scopes\CustomerScope::class)
                    ->where('sales_id', $sales->id)
                    ->whereDate('next_follow_up_at', today())
                    ->count();

                if ($todayCount > 0) {
                    $notif = Notification::make()
                        ->title('تنبيه: متابعات اليوم')
                        ->body("لديك {$todayCount} عملاء مجدولين للمتابعة اليوم.")
                        ->icon('heroicon-o-calendar')
                        ->info()
                        ->actions([
                            Action::make('view')
                                ->label('عرض المتابعات اليومية')
                                ->url(route('filament.admin.resources.customers.index')),
                        ]);

                    $this->saveDirectNotification($sales, $notif);
                    $notificationsSent++;
                }
            }
        }

        if ($notificationsSent > 0) {
            $this->info("تم إرسال عدد {$notificationsSent} إشعارات بنجاح إلى قاعدة البيانات.");
        } else {
            $this->info('لم يتم العثور على متابعات معلقة أو عملاء بحاجة لإشعارات حالياً.');
        }
    }

    private function saveDirectNotification(User $user, Notification $notification): void
    {
        DB::table('notifications')->insert([
            'id' => Str::orderedUuid()->toString(),
            'type' => 'Filament\Notifications\Notification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => $user->id,
            'data' => json_encode(array_merge($notification->toArray(), [
                'duration' => 'persistent',
                'format' => 'filament',
            ])),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

