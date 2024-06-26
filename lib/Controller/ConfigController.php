<?php
/**
 * @copyright hugo.duret@cea.fr 2024
 *
 * Use of this source code is governed by an MIT-style
 * license that can be found in the LICENSE file or at
 * https://opensource.org/licenses/MIT.
 *  
 * Contributors:
 *  @author Hugo Duret  hugo.duret@cea.fr - Initial implementation
 *
 */

namespace OCA\Forgejo\Controller;

use DateTime;
use OCA\Forgejo\Reference\ForgejoReferenceProvider;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use OCP\IConfig;
use OCP\IL10N;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Forgejo\Service\ForgejoAPIService;
use OCA\Forgejo\AppInfo\Application;
use OCP\PreConditionNotMetException;

class ConfigController extends Controller
{

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private IL10N $l,
		private IInitialState $initialStateService,
		private ForgejoAPIService $forgejoAPIService,
		private ForgejoReferenceProvider $forgejoReferenceProvider,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * set config values
	 * @NoAdminRequired
	 *
	 * @param array $values
	 * @return DataResponse
	 * @throws PreConditionNotMetException
	 */
	public function setConfig(array $values): DataResponse
	{
		// revoke the oauth token if needed
		if (isset($values['token']) && $values['token'] === '') {
			$tokenType = $this->config->getUserValue($this->userId, Application::APP_ID, 'token_type');
			if ($tokenType === 'oauth') {
				$this->forgejoAPIService->revokeOauthToken($this->userId);
			}
		}

		foreach ($values as $key => $value) {
			$this->config->setUserValue($this->userId, Application::APP_ID, $key, $value);
		}
		$result = [];

		if (isset($values['token'])) {
			// if the token is set, cleanup refresh token and expiration date
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_type');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'refresh_token');
			$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token_expires_at');
			$this->forgejoReferenceProvider->invalidateUserCache($this->userId);

			if ($values['token'] && $values['token'] !== '') {
				$info = $this->storeUserInfo();
				if (isset($info['error'])) {
					return new DataResponse(['error' => $info['error']], Http::STATUS_BAD_REQUEST);
				}
				$result['user_name'] = $info['username'] ?? '';
				$result['user_displayname'] = $info['userdisplayname'] ?? '';
				// store token type if it's valid (so we have a user name)
				if ($result['user_name'] !== '') {
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_type', 'personal');
				}
			} else {
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_id');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_name');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'user_displayname');
				$this->config->deleteUserValue($this->userId, Application::APP_ID, 'token');
				$result['user_name'] = '';
			}
		}
		return new DataResponse($result);
	}

	/**
	 * set admin config values
	 *
	 * @param array $values
	 * @return DataResponse
	 */
	public function setAdminConfig(array $values): DataResponse
	{
		foreach ($values as $key => $value) {
			$this->config->setAppValue(Application::APP_ID, $key, $value);
		}
		return new DataResponse(1);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $user_name
	 * @param string $user_displayname
	 * @return TemplateResponse
	 */
	public function popupSuccessPage(string $user_name, string $user_displayname): TemplateResponse
	{
		$this->initialStateService->provideInitialState('popup-data', ['user_name' => $user_name, 'user_displayname' => $user_displayname]);
		return new TemplateResponse(Application::APP_ID, 'popupSuccess', [], TemplateResponse::RENDER_AS_GUEST);
	}

	/**
	 * receive oauth code and get oauth access token
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 *
	 * @param string $code
	 * @param string $state
	 * @return RedirectResponse
	 * @throws PreConditionNotMetException
	 */
	public function oauthRedirect(string $code = '', string $state = ''): RedirectResponse
	{
		$configState = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_state');
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');

		// anyway, reset state
		$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_state');

		if ($clientID and $clientSecret and $configState !== '' and $configState === $state) {
			$redirect_uri = $this->config->getUserValue($this->userId, Application::APP_ID, 'redirect_uri');
			$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_FORGEJO_URL) ?: Application::DEFAULT_FORGEJO_URL;
			$forgejoUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
			$result = $this->forgejoAPIService->requestOAuthAccessToken($forgejoUrl, [
				'client_id' => $clientID,
				'client_secret' => $clientSecret,
				'code' => $code,
				'redirect_uri' => $redirect_uri,
				'grant_type' => 'authorization_code'
			], 'POST');
			if (isset($result['access_token'])) {
				$this->forgejoReferenceProvider->invalidateUserCache($this->userId);
				$accessToken = $result['access_token'];
				$refreshToken = $result['refresh_token'] ?? '';
				if (isset($result['expires_in'])) {
					$nowTs = (new Datetime())->getTimestamp();
					$expiresAt = $nowTs + (int) $result['expires_in'];
					$this->config->setUserValue($this->userId, Application::APP_ID, 'token_expires_at', $expiresAt);
				}
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token', $accessToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'refresh_token', $refreshToken);
				$this->config->setUserValue($this->userId, Application::APP_ID, 'token_type', 'oauth');
				$userInfo = $this->storeUserInfo();

				$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0') === '1';
				if ($usePopup) {
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('integration_forgejo.config.popupSuccessPage', [
							'user_name' => $userInfo['username'] ?? '',
							'user_displayname' => $userInfo['userdisplayname'] ?? '',
						])
					);
				} else {
					$oauthOrigin = $this->config->getUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					$this->config->deleteUserValue($this->userId, Application::APP_ID, 'oauth_origin');
					if ($oauthOrigin === 'settings') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
							'?forgejoToken=success'
						);
					} elseif ($oauthOrigin === 'dashboard') {
						return new RedirectResponse(
							$this->urlGenerator->linkToRoute('dashboard.dashboard.index')
						);
					}
					return new RedirectResponse(
						$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
						'?forgejoToken=success'
					);
				}
			}
			$result = $this->l->t('Error getting OAuth access token. ' . $result['error']);
		} else {
			$result = $this->l->t('Error during OAuth exchanges');
		}
		return new RedirectResponse(
			$this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']) .
			'?forgejoToken=error&message=' . urlencode($result)
		);
	}

	/**
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	private function storeUserInfo(): array
	{
		$info = $this->forgejoAPIService->request($this->userId, 'user');
		if (isset($info['username']) && isset($info['id'])) {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', $info['id']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', $info['username']);
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_displayname', $info['name']);
			return [
				'username' => $info['username'],
				'userid' => $info['id'],
				'userdisplayname' => $info['name'],
			];
		} else {
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_id', '');
			$this->config->setUserValue($this->userId, Application::APP_ID, 'user_name', '');
			return $info;
		}
	}
}
