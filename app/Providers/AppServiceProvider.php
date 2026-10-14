<?php

namespace App\Providers;

use App\Contracts\Mail\CampaignMailer;
use App\Contracts\Mail\GmailApi;
use App\Contracts\Mail\MailboxReader;
use App\Services\Mail\CampaignMailerResolver;
use App\Services\Mail\GoogleGmailApi;
use App\Services\Mail\MailboxReaderResolver;
use Carbon\CarbonImmutable;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
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

        $proxies = config('app.trusted_proxies');
        TrustProxies::at($proxies === ['*'] ? '*' : $proxies);

        if (app()->isProduction()) {
            URL::forceRootUrl(config('app.url'));
            URL::forceScheme((string) parse_url(config('app.url'), PHP_URL_SCHEME) ?: 'https');
        }

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
