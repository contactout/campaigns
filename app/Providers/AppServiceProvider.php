<?php

namespace App\Providers;

use App\Contracts\Mail\CampaignMailer;
use App\Contracts\Mail\GmailApi;
use App\Contracts\Mail\MailboxReader;
use App\Services\Mail\CampaignMailerResolver;
use App\Services\Mail\GoogleGmailApi;
use App\Services\Mail\MailboxReaderResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CampaignMailer::class, CampaignMailerResolver::class);
        $this->app->bind(MailboxReader::class, MailboxReaderResolver::class);
        $this->app->bind(GmailApi::class, GoogleGmailApi::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
