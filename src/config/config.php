<?php
/**
 * Configuration for OAuth
 *
 * @return  array
 */
return array(
    'http_client'       => 'Stream',
    'extra_providers'   => array(
        'Projectplace'  => '\Crockett95\ProjectPlace\OAuthService'
    ),
    'settings' => array(
        'Facebook'  => array(
            'client_id'     => '',
            'client_secret' => '',
            'scope'         => array(),
        ),
        'Projectplace'  => array(
            'client_id'     => $_ENV['PROJECTPLACE_API_ID'],
            'client_secret' => $_ENV['PROJECTPLACE_API_SECRET'],
            'callback'      => $_ENV['PROJECTPLACE_API_CALLBACK'],
        )
    )
);