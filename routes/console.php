<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Removes unverified registrations and expired one-time passwords.
Schedule::command('model:prune')->hourly()->withoutOverlapping();

// Removes customer API tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Applies sale prices and sale events that start or end (stored per variant for storefront filters).
Schedule::command('prices:refresh --due')->everyMinute()->withoutOverlapping();
