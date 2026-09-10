<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Redirect inactive users away from the Filament panel with a clear notification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if ($user && ! $user->is_active) {
            Filament::auth()->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Notification::make()
                ->title('الحساب غير نشط')
                ->body('تم تعطيل حسابك. يرجى التواصل مع المدير.')
                ->danger()
                ->send();

            return redirect()->route('filament.admin.auth.login')
                ->withErrors(['email' => 'تم تعطيل حسابك. يرجى التواصل مع المدير.']);
        }

        return $next($request);
    }
}
