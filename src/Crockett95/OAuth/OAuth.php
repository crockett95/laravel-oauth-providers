<?php
/**
 * This file contains the main implementation for the Laravel OAuth integration.
 * 
 * @author      Steve Crockett <crockett95@gmail.com>
 * @version     0.0.0
 * @package     Crockett95\OAuth
 * @license     http://opensource.org/licenses/MIT  MIT
 */

namespace Crockett95\OAuth;

// Laravel Classes
use \Config;
use \URL;
use \Redirect;
use \Input;

// OAuth Library classes
use \OAuth\ServiceFactory;
use \OAuth\Common\Consumer\Credentials;
use \OAuth\Common\Service\AbstractService;
use \OAuth\Common\Storage\TokenStorageInterface;

/**
 * The main class for the OAuth library wrapper
 */
class OAuth
{

    /**
     * The Laravel application
     *
     * @var     Illuminate\Foundation\Application   $app    The laravel application
     */
    protected $app;

    /**
     * The OAuth Service Factory instance
     *
     * @var     OAuth\ServiceFactory    $serviceFactory     The OAuth Service factory
     */
    protected $serviceFactory;

    /**
     * The current service
     *
     * @var     \OAuth\Common\Service\ServiceInterface  $service
     */
    protected $service;

    /**
     * The current storage
     *
     * @var     \OAuth\Common\Storage\TokenStorageInterface     $service
     */
    protected $storage;

    /**
     * Constructor
     *
     * @param   Illuminate\Foundation\Application   $app    The laravel application
     * @param   OAuth\ServiceFactory    $serviceFactory     The OAuth Service factory
     */
    public function __construct($app = null, ServiceFactory $serviceFactory = null)
    {
        if (null === $serviceFactory && null === $app) {
            $serviceFactory = new ServiceFactory();
        } elseif (null === $serviceFactory) {
            $serviceFactory = $app->make('oauth.serviceFactory');
        }

        $this->app = $app;
        $this->serviceFactory = $serviceFactory;
        $this->setExtraProviders();
        $this->storage = $this->createStorage(true);
        $this->setHttpClient(Config::get('oauth::http_client'));
    }

    /**
     * Return a provider for a specific OAuth service implementation
     *
     * @param   string          $provider       The name of the service
     * @param   string|null     $callbackUrl    The URL to be used for the credentials
     * @param   array|null      $scope          The scope of the access (OAuth2 only)
     * @return  \OAuth\Common\Service\AbstractService
     */
    public function provider(
        $provider,
        $callbackUrl = null,
        array $scope = null,
        $storage = null
    ) {
        $config = $this->getConfig($provider);

        if (null !== $scope) {
            $config['scope'] = $scope;
        } elseif (!isset($config['scope'])) {
            $config['scope'] = array();
        }

        $storage = $storage ?: $this->storage;

        $credentials = new Credentials(
            $config['client_id'],
            $config['client_secret'],
            $this->makeCallbackUrl($callbackUrl, $config)
        );

        return $this->serviceFactory->createService(
            $provider,          // $serviceName,
            $credentials,       // CredentialsInterface $credentials,
            $storage,           // TokenStorageInterface $storage,
            $config['scope']    // $scopes = array(),
        );
    }

    /**
     * Use this instance with to work with a service
     *
     * @param   string          $provider       The name of the service
     * @param   string|null     $callbackUrl    The URL to be used for the credentials
     * @param   array|null      $scope          The scope of the access (OAuth2 only)
     * @return  \Crockett95\OAuth\OAuth
     */
    public function useProvider(
        $provider,
        $callbackUrl = null,
        array $scope = null
    ) {
        $this->service = $this->provider($provider, $callbackUrl, $scope);

        return $this;
    }

    /**
     * Set up the access tokens
     */
    public function fromInput(
        AbstractService $service = null
    ) {
        if (null === $service && isset($this->service)) {
            $service = $this->service;
        } elseif (null === $service) {
            throw new Exception("An OAuth service is required", 1);
        }


        if (is_a($this->service, '\OAuth\OAuth1\Service\AbstractService')) {
            $token = $this->storage->retrieveAccessToken($service->service());

            $service->requestAccessToken(
                Input::get('oauth_token'),
                Input::get('oauth_verifier'),
                $token->getRequestTokenSecret()
            );
        } elseif (is_a($this->service, '\OAuth\OAuth2\Service\AbstractService')) {
            $token = $service->requestAccessToken(Input::get('code'));
        }

        return $service;
    }

    /**
     * Set the HTTP Client
     *
     * @param   string|ClientInterface  Client name as string without `Client`, or object
     * @throws  Exception   If the object provided does not implement the ClientInterface
     */
    public function setHttpClient($client)
    {
        if (null === $client) {
            return;
        } elseif (is_string($client)) {
            $client_class = "\OAuth\Common\Http\Client\\${client}Client";
            $client = new $client_class();
        } elseif (!in_array('ClientInterface', class_implements($client))) {
            throw new Exception("HTTP Client must implement ClientInterface", 1);
        }

        $this->serviceFactory->setHttpClient($client);
    }

    /**
     * Make a storage instance
     */
    public function createStorage($save = false)
    {
        $storage = $this->app->make('oauth.storage');

        if ($save) {
            $this->storage = $storage;
        }

        return $storage;
    }

    /**
     * Redirect to the authorization page for the service
     *
     * @param   string          $provider       The name of the service
     * @param   string|null     $callbackUrl    The URL to be used for the credentials
     * @param   array|null      $scope          The scope of the access (OAuth2 only)
     * @return  \Illuminate\Http\RedirectResponse
     */
    public function authorizationRedirect(
        $provider,
        $callbackUrl = null,
        array $scope = null
    ) {
        $this->service = $this->provider($provider, $callbackUrl, $scope);

        if (is_a($this->service, '\OAuth\OAuth1\Service\AbstractService')) {
            $token = $this->service->requestRequestToken();

            $url = $this->service->getAuthorizationUri(
                array('oauth_token' => $token->getRequestToken())
            );
        } elseif (is_a($this->service, '\OAuth\OAuth2\Service\AbstractService')) {
            $url = $this->service->getAuthorizationUri();
        }

        return Redirect::to((string) $url);
    }

    /**
     * Check if we received a response with Authorization
     */
    public function hasAccessInput()
    {
        return (Input::has('code') || 
            (Input::has('oauth_token') && Input::has('oauth_verifier')));
    }

    /**
     * Check if we received a response with Authorization
     */
    public function hasAccessToken($service)
    {
        return $this->storage->hasAccessToken($service);
    }

    /**
     * Allow pass-through of methods to service
     */
    public function __call($name, $arguments = array())
    {
        if (method_exists($this->service, $name)) {
            return call_user_func_array(array($this->service, $name), $arguments);
        }
    }

    /**
     * Get the configuration for a service
     *
     * @param   string  $name   The name of the service
     * @return  array   An array of the parameters
     */
    protected function getConfig($name)
    {
        $config = Config::get("oauth::settings.$name");

        return $config;
    }

    /**
     * Set extra providers based on the configuration settings
     */
    protected function setExtraProviders()
    {
        $providers = Config::get('oauth::extra_providers');

        if (!$providers) return;

        foreach ($providers as $name => $class) {
            $this->serviceFactory->registerService($name, $class);
        }
    }

    /**
     * Given the settings, return the correct callback URL
     *
     * @param   string  $userUrl    The url provided when the builder was called
     * @param   array   $config     The configuration array
     * @return  string  The resolved URL
     */
    protected function makeCallbackUrl($userUrl, $config)
    {
        if (null !== $userUrl) {
            return $userUrl;
        } elseif (isset($config['callback']) && $config['callback']) {
            return $config['callback'];
        } else {
            return URL::current();
        }
    }

}