<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/integrations', 'pages::settings.integrations')->name('integrations.edit');

    Route::livewire('settings/notifications', 'pages::settings.notifications')->name('notifications.edit');

    Route::post('settings/notifications/push-subscription', [PushSubscriptionController::class, 'store'])
        ->name('notifications.push-subscription.store');
    Route::delete('settings/notifications/push-subscription', [PushSubscriptionController::class, 'destroy'])
        ->name('notifications.push-subscription.destroy');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('security.edit');
});
