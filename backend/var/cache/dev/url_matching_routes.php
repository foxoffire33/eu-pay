<?php

/**
 * This file has been auto-generated
 * by the Symfony Routing Component.
 */

return [
    false, // $matchHost
    [ // $staticRoutes
        '/api/account/create' => [[['_route' => 'app_account_create', '_controller' => 'App\\Controller\\AccountController::create'], null, ['POST' => 0], null, false, false, null]],
        '/api/account/balance' => [[['_route' => 'app_account_balance', '_controller' => 'App\\Controller\\AccountController::balance'], null, ['GET' => 0], null, false, false, null]],
        '/api/account/transactions' => [[['_route' => 'app_account_transactions', '_controller' => 'App\\Controller\\AccountController::transactions'], null, ['GET' => 0], null, false, false, null]],
        '/api/me' => [[['_route' => 'app_auth_me', '_controller' => 'App\\Controller\\AuthController::me'], null, ['GET' => 0], null, false, false, null]],
        '/api/me/rotate-key' => [[['_route' => 'app_auth_rotatekey', '_controller' => 'App\\Controller\\AuthController::rotateKey'], null, ['POST' => 0], null, false, false, null]],
        '/api/cards' => [[['_route' => 'app_card_list', '_controller' => 'App\\Controller\\CardController::list'], null, ['GET' => 0], null, false, false, null]],
        '/api/cards/virtual' => [[['_route' => 'app_card_createvirtual', '_controller' => 'App\\Controller\\CardController::createVirtual'], null, ['POST' => 0], null, false, false, null]],
        '/api/digital-euro/status' => [[['_route' => 'app_digitaleuro_status', '_controller' => 'App\\Controller\\DigitalEuroController::status'], null, ['GET' => 0], null, false, false, null]],
        '/api/digital-euro/account' => [[['_route' => 'app_digitaleuro_openaccount', '_controller' => 'App\\Controller\\DigitalEuroController::openAccount'], null, ['POST' => 0], null, false, false, null]],
        '/api/digital-euro/pay/p2p' => [[['_route' => 'app_digitaleuro_payp2p', '_controller' => 'App\\Controller\\DigitalEuroController::payP2P'], null, ['POST' => 0], null, false, false, null]],
        '/api/digital-euro/pay/pos' => [[['_route' => 'app_digitaleuro_paypos', '_controller' => 'App\\Controller\\DigitalEuroController::payPos'], null, ['POST' => 0], null, false, false, null]],
        '/api/gdpr/export' => [[['_route' => 'app_gdpr_exportdata', '_controller' => 'App\\Controller\\GdprController::exportData'], null, ['GET' => 0], null, false, false, null]],
        '/api/gdpr/erase' => [[['_route' => 'app_gdpr_erasedata', '_controller' => 'App\\Controller\\GdprController::eraseData'], null, ['POST' => 0], null, false, false, null]],
        '/api/gdpr/consent' => [
            [['_route' => 'app_gdpr_getconsent', '_controller' => 'App\\Controller\\GdprController::getConsent'], null, ['GET' => 0], null, false, false, null],
            [['_route' => 'app_gdpr_updateconsent', '_controller' => 'App\\Controller\\GdprController::updateConsent'], null, ['PATCH' => 0], null, false, false, null],
        ],
        '/api/legal/privacy-policy' => [[['_route' => 'app_gdpr_privacypolicy', '_controller' => 'App\\Controller\\GdprController::privacyPolicy'], null, ['GET' => 0], null, false, false, null]],
        '/api/legal/imprint' => [[['_route' => 'app_gdpr_imprint', '_controller' => 'App\\Controller\\GdprController::imprint'], null, ['GET' => 0], null, false, false, null]],
        '/api/legal/withdrawal' => [[['_route' => 'app_gdpr_withdrawalrights', '_controller' => 'App\\Controller\\GdprController::withdrawalRights'], null, ['GET' => 0], null, false, false, null]],
        '/api/hce/provision' => [[['_route' => 'app_hce_provision', '_controller' => 'App\\Controller\\HceController::provision'], null, ['POST' => 0], null, false, false, null]],
        '/api/hce/tokens' => [[['_route' => 'app_hce_listtokens', '_controller' => 'App\\Controller\\HceController::listTokens'], null, ['GET' => 0], null, false, false, null]],
        '/api/p2p/send/user' => [[['_route' => 'app_p2p_sendtouser', '_controller' => 'App\\Controller\\P2PController::sendToUser'], null, ['POST' => 0], null, false, false, null]],
        '/api/p2p/send/iban' => [[['_route' => 'app_p2p_sendtoiban', '_controller' => 'App\\Controller\\P2PController::sendToIban'], null, ['POST' => 0], null, false, false, null]],
        '/api/p2p/history' => [[['_route' => 'app_p2p_history', '_controller' => 'App\\Controller\\P2PController::history'], null, ['GET' => 0], null, false, false, null]],
        '/api/p2p/banks' => [[['_route' => 'app_p2p_banks', '_controller' => 'App\\Controller\\P2PController::banks'], null, ['GET' => 0], null, false, false, null]],
        '/api/topup/ideal' => [[['_route' => 'app_topup_initiateideal', '_controller' => 'App\\Controller\\TopUpController::initiateIdeal'], null, ['POST' => 0], null, false, false, null]],
        '/api/topup/sepa' => [[['_route' => 'app_topup_initiatesepa', '_controller' => 'App\\Controller\\TopUpController::initiateSepa'], null, ['POST' => 0], null, false, false, null]],
        '/api/topup/callback' => [[['_route' => 'app_topup_callback', '_controller' => 'App\\Controller\\TopUpController::callback'], null, ['GET' => 0], null, false, false, null]],
        '/api/topup/history' => [[['_route' => 'app_topup_history', '_controller' => 'App\\Controller\\TopUpController::history'], null, ['GET' => 0], null, false, false, null]],
        '/api/topup/banks' => [[['_route' => 'app_topup_banks', '_controller' => 'App\\Controller\\TopUpController::banks'], null, ['GET' => 0], null, false, false, null]],
        '/api/passkey/register/options' => [[['_route' => 'app_webauthn_registeroptions', '_controller' => 'App\\Controller\\WebAuthnController::registerOptions'], null, ['POST' => 0], null, false, false, null]],
        '/api/passkey/register' => [[['_route' => 'app_webauthn_register', '_controller' => 'App\\Controller\\WebAuthnController::register'], null, ['POST' => 0], null, false, false, null]],
        '/api/passkey/login/options' => [[['_route' => 'app_webauthn_loginoptions', '_controller' => 'App\\Controller\\WebAuthnController::loginOptions'], null, ['POST' => 0], null, false, false, null]],
        '/api/passkey/login' => [[['_route' => 'app_webauthn_login', '_controller' => 'App\\Controller\\WebAuthnController::login'], null, ['POST' => 0], null, false, false, null]],
    ],
    [ // $regexpList
        0 => '{^(?'
                .'|/api/(?'
                    .'|cards/([^/]++)/(?'
                        .'|activate(*:41)'
                        .'|block(*:53)'
                        .'|unblock(*:67)'
                        .'|ephemeral\\-key(*:88)'
                    .')'
                    .'|digital\\-euro/balance/([^/]++)(*:126)'
                    .'|hce/(?'
                        .'|payload/([^/]++)(*:157)'
                        .'|refresh/([^/]++)(*:181)'
                        .'|deactivate/([^/]++)(*:208)'
                    .')'
                .')'
            .')/?$}sDu',
    ],
    [ // $dynamicRoutes
        41 => [[['_route' => 'app_card_activate', '_controller' => 'App\\Controller\\CardController::activate'], ['id'], ['POST' => 0], null, false, false, null]],
        53 => [[['_route' => 'app_card_block', '_controller' => 'App\\Controller\\CardController::block'], ['id'], ['POST' => 0], null, false, false, null]],
        67 => [[['_route' => 'app_card_unblock', '_controller' => 'App\\Controller\\CardController::unblock'], ['id'], ['POST' => 0], null, false, false, null]],
        88 => [[['_route' => 'app_card_ephemeralkey', '_controller' => 'App\\Controller\\CardController::ephemeralKey'], ['id'], ['POST' => 0], null, false, false, null]],
        126 => [[['_route' => 'app_digitaleuro_balance', '_controller' => 'App\\Controller\\DigitalEuroController::balance'], ['deaId'], ['GET' => 0], null, false, true, null]],
        157 => [[['_route' => 'app_hce_payload', '_controller' => 'App\\Controller\\HceController::payload'], ['tokenId'], ['GET' => 0], null, false, true, null]],
        181 => [[['_route' => 'app_hce_refresh', '_controller' => 'App\\Controller\\HceController::refresh'], ['tokenId'], ['POST' => 0], null, false, true, null]],
        208 => [
            [['_route' => 'app_hce_deactivate', '_controller' => 'App\\Controller\\HceController::deactivate'], ['tokenId'], ['POST' => 0], null, false, true, null],
            [null, null, null, null, false, false, 0],
        ],
    ],
    null, // $checkCondition
];
