<?php
namespace App\Grant;

use League\OAuth2\Server\Grant\AuthCodeGrant as AcGrant;
use League\OAuth2\Server\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entity\ClientEntity;
use League\OAuth2\Server\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Event;
use League\OAuth2\Server\Exception;
use League\OAuth2\Server\Util\SecureKey;

class AuthCodeGrant extends AcGrant
{
    public function checkAuthorizeParams()
    {
        // Get required params
        $clientId = $this->server->getRequest()->query->get('client_id', null);
        if (is_null($clientId)) {
            throw new Exception\InvalidRequestException('client_id');
        }

        $redirectUri = $this->server->getRequest()->query->get('redirect_uri', null);

        $tempu = parse_url($redirectUri);
        //获取顶级域
        preg_match('/[\w][\w-]*\.(?:com\.cn|com|cn|co|net|org|gov|cc|biz|info)(\/|$)/isU', $tempu['host'], $domain);

        $Uri = rtrim($domain[0], '/');

        if (is_null($redirectUri)) {
            throw new Exception\InvalidRequestException('redirect_uri');
        }
        // Validate client ID and redirect URI
        $client = $this->server->getClientStorage()->get(
            $clientId,
            null,
            $Uri,
            $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            throw new Exception\InvalidClientException();
        }

        $state = $this->server->getRequest()->query->get('state', null);
        if ($this->server->stateParamRequired() === true && is_null($state)) {
            throw new Exception\InvalidRequestException('state', $redirectUri);
        }

        $responseType = $this->server->getRequest()->query->get('response_type', null);
        if (is_null($responseType)) {
            throw new Exception\InvalidRequestException('response_type', $redirectUri);
        }

        // Ensure response type is one that is recognised
        if (!in_array($responseType, $this->server->getResponseTypes())) {
            throw new Exception\UnsupportedResponseTypeException($responseType, $redirectUri);
        }

        // Validate any scopes that are in the request
        $scopeParam = $this->server->getRequest()->query->get('scope', '');
        $scopes = $this->validateScopes($scopeParam, $client, $redirectUri);

        return [
            'client'        => $client,
            'redirect_uri'  => $redirectUri,
            'state'         => $state,
            'response_type' => $responseType,
            'scopes'        => $scopes
        ];
    }

    public function completeFlow()
    {
        // Get the required params
        $clientId = $this->server->getRequest()->request->get('client_id', $this->server->getRequest()->getUser());
        if (is_null($clientId)) {
            throw new Exception\InvalidRequestException('client_id');
        }

        $clientSecret = $this->server->getRequest()->request->get('client_secret',
            $this->server->getRequest()->getPassword());
        if ($this->shouldRequireClientSecret() && is_null($clientSecret)) {
            throw new Exception\InvalidRequestException('client_secret');
        }

        $redirectUri = $this->server->getRequest()->request->get('redirect_uri', null);
        if (is_null($redirectUri)) {
            throw new Exception\InvalidRequestException('redirect_uri');
        }
        $tempu = parse_url($redirectUri);
        //获取顶级域
        preg_match('/[\w][\w-]*\.(?:com\.cn|com|cn|co|net|org|gov|cc|biz|info)(\/|$)/isU', $tempu['host'], $domain);

        $Uri = rtrim($domain[0], '/');
        // Validate client ID and client secret
        $client = $this->server->getClientStorage()->get(
            $clientId,
            $clientSecret,
            $Uri,
            $this->getIdentifier()
        );

        if (($client instanceof ClientEntity) === false) {
            $this->server->getEventEmitter()->emit(new Event\ClientAuthenticationFailedEvent($this->server->getRequest()));
            throw new Exception\InvalidClientException();
        }

        // Validate the auth code
        $authCode = $this->server->getRequest()->request->get('code', null);
        if (is_null($authCode)) {
            throw new Exception\InvalidRequestException('code');
        }

        $code = $this->server->getAuthCodeStorage()->get($authCode);
        if (($code instanceof AuthCodeEntity) === false) {
            throw new Exception\InvalidRequestException('code');
        }

        // Ensure the auth code hasn't expired
        if ($code->isExpired() === true) {
            throw new Exception\InvalidRequestException('code');
        }

        // Check redirect URI presented matches redirect URI originally used in authorize request

        if ($code->getRedirectUri() !== $redirectUri) {
            throw new Exception\InvalidRequestException('redirect_uri');
        }

        $session = $code->getSession();
        $session->associateClient($client);

        $authCodeScopes = $code->getScopes();

        // Generate the access token
        $accessToken = new AccessTokenEntity($this->server);
        $accessToken->setId(SecureKey::generate());
        $accessToken->setExpireTime($this->getAccessTokenTTL() + time());

        foreach ($authCodeScopes as $authCodeScope) {
            $session->associateScope($authCodeScope);
        }

        foreach ($session->getScopes() as $scope) {
            $accessToken->associateScope($scope);
        }

        $this->server->getTokenType()->setSession($session);
        $this->server->getTokenType()->setParam('access_token', $accessToken->getId());
        $this->server->getTokenType()->setParam('expires_in', $this->getAccessTokenTTL());

        // Associate a refresh token if set
        if ($this->server->hasGrantType('refresh_token')) {
            $refreshToken = new RefreshTokenEntity($this->server);
            $refreshToken->setId(SecureKey::generate());
            $refreshToken->setExpireTime($this->server->getGrantType('refresh_token')->getRefreshTokenTTL() + time());
            $this->server->getTokenType()->setParam('refresh_token', $refreshToken->getId());
        }

        // Expire the auth code
        $code->expire();

        // Save all the things
        $accessToken->setSession($session);
        $accessToken->save();

        if (isset($refreshToken) && $this->server->hasGrantType('refresh_token')) {
            $refreshToken->setAccessToken($accessToken);
            $refreshToken->save();
        }

        return $this->server->getTokenType()->generateResponse();
    }
}