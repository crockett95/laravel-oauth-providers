<?php
/**
 * This file provides the ServiceProvider for the OAuth Laravel implementation.
 *
 * @author      Steve Crockett <crockett95@gmail.com>
 * @version     0.0.0
 * @package     Crockett95\OAuth
 * @license     http://opensource.org/licenses/MIT  MIT
 */

namespace Crockett95\OAuth;

use Illuminate\Support\ServiceProvider;
use OAuth\ServiceFactory;
use OAuth\Common\Storage\Session;

/**
 * Class for Laravel Service Provider implementation
 *
 * @package Crockett95\OAuth
 */
class OAuthServiceProvider extends ServiceProvider {

    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->package('crockett95/o-auth', 'oauth');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Bind the ServiceFactory creation if it doesn't exist
        $this->app->bindIf('oauth.serviceFactory', function ()
        {
            return new ServiceFactory();
        });

        // Bind the Storage creation if it doesn't exist
        $this->app->bindIf('oauth.storage', function ()
        {
            return new Session();
        });

        // Bind the OAuth class instance
        $this->app->singleton('oauth', function ($app)
        {
            return new OAuth($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('oauth.serviceFactory', 'oauth.storage');
    }

}
