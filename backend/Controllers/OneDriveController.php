<?php

/*
 * This file is part of the FileGator package.
 *
 * (c) John Nguyen <john.nguyen09@outlook.com>
 *
 * For the full copyright and license information, please view the LICENSE file
 */

namespace Filegator\Controllers;

use Krizalys\Onedrive\Onedrive;
use Filegator\Services\Tmpfs\TmpfsInterface;
use Filegator\Config\Config;
use Filegator\Kernel\Response;
use Filegator\Kernel\Request;
use Filegator\Services\Session\SessionStorageInterface;

class OneDriveController
{
    /** @var TmpfsInterface */
    private $tmpfs;

    /** @var Config */
    private $config;

    /** @var SessionStorageInterface */
    private $session;

    public function __construct(Config $config, TmpfsInterface $tmpfs, SessionStorageInterface $session)
    {
        $this->config = $config;
        $this->tmpfs = $tmpfs;
        $this->session = $session;
    }

    public function authorise(Request $request, Response $response)
    {
        $clientId = $this->config->get('microsoft_client_id');
        $redirectUri = $this->config->get('app_url') . '/redirect.php';

        $client = Onedrive::client($clientId);
        $url = $client->getLogInUrl([
            'Files.ReadWrite.All',
            'offline_access',
        ], $redirectUri);
        $this->session->set('onedrive.client.state', $client->getState());
        return $response->redirect($url);
    }

    public function redirect(Request $request, Response $response)
    {
        $code = $request->query->get('code');
        if (is_null($code)) {
            return;
        }
        $state = $this->session->get('onedrive.client.state');
        if (is_null($state)) {
            return;
        }
        $client = Onedrive::client($this->config->get('microsoft_client_id'), [
            'state' => $state,
        ]);
        try {
            $client->obtainAccessToken($this->config->get('microsoft_client_secret'), $code);
        } catch (\Throwable $ex) {
            echo '<pre>';
            var_dump($ex);
            var_dump((string) $ex->getResponse()->getBody());
            die();
        }
        $this->session->set('onedrive.client.state', $client->getState());
        $this->tmpfs->write('onedrive.client.state', serialize($client->getState()));
        return $response->redirect($this->config->get('app_url'));
    }
}
