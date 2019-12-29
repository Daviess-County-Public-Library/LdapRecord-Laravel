<?php

namespace LdapRecord\Laravel;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Commands\Import;
use LdapRecord\Laravel\Commands\MakeDomain;
use LdapRecord\Laravel\Listeners\BindsLdapUserModel;

class LdapAuthServiceProvider extends ServiceProvider
{
    /**
     * The events to log (if enabled).
     *
     * @var array
     */
    protected $events = [
        Events\Importing::class                 => Listeners\LogImport::class,
        Events\Synchronized::class              => Listeners\LogSynchronized::class,
        Events\Synchronizing::class             => Listeners\LogSynchronizing::class,
        Events\Authenticated::class             => Listeners\LogAuthenticated::class,
        Events\Authenticating::class            => Listeners\LogAuthentication::class,
        Events\AuthenticationFailed::class      => Listeners\LogAuthenticationFailure::class,
        Events\AuthenticationRejected::class    => Listeners\LogAuthenticationRejection::class,
        Events\AuthenticationSuccessful::class  => Listeners\LogAuthenticationSuccess::class,
        Events\DiscoveredWithCredentials::class => Listeners\LogDiscovery::class,
        Events\AuthenticatedWithWindows::class  => Listeners\LogWindowsAuth::class,
        Events\AuthenticatedModelTrashed::class => Listeners\LogTrashedModel::class,
    ];

    /**
     * Run service provider boot operations.
     *
     * @return void
     */
    public function boot()
    {
        $this->commands([Import::class, MakeDomain::class]);

        Auth::provider('ldap', function ($app, array $config) {
            /** @var Domain $domain */
            $domain = app(DomainRegistrar::class)->get($config['domain']);

            return $domain instanceof SynchronizedDomain ?
                new DatabaseUserProvider($domain, $app['hash']) :
                new NoDatabaseUserProvider($domain);
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Here we will register the event listener that will bind the users LDAP
        // model to their Eloquent model upon authentication (if configured).
        // This allows us to utilize their LDAP model right
        // after authentication has passed.
        Event::listen([Login::class, Authenticated::class], BindsLdapUserModel::class);

        if ($this->isLogging()) {
            // If logging is enabled, we will set up our event listeners that
            // log each event fired throughout the authentication process.
            foreach ($this->events as $event => $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Determines if authentication requests are logged.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return Config::get('ldap.logging.enabled', false);
    }
}