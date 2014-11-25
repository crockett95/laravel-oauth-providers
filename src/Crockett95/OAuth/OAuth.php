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

use OAuth\ServiceFactory;

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
     * Constructor
     *
     * @param   Illuminate\Foundation\Application   $app    The laravel application
     * @param   OAuth\ServiceFactory    $serviceFactory     The OAuth Service factory
     */
    public function __construct($app, ServiceFactory $serviceFactory = null)
    {
        if (null === $serviceFactory && null === $app) {
            $serviceFactory = new ServiceFactory();
        } elseif (null === $serviceFactory) {
            $serviceFactory = $app->make('oauth.serviceFactory');
        }

        $this->serviceFactory = $serviceFactory;
    }

    public function helloWorld()
    {
        return "Hello world";
    }

}