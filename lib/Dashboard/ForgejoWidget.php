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

namespace OCA\Forgejo\Dashboard;

use OCP\AppFramework\Services\IInitialState;
use OCP\Dashboard\IWidget;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

use OCA\Forgejo\AppInfo\Application;

class ForgejoWidget implements IWidget
{

	public function __construct(
		private IL10N $l10n,
		private IConfig $config,
		private IURLGenerator $url,
		private IInitialState $initialStateService,
		private ?string $userId
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string
	{
		return 'forgejo_todos';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string
	{
		return $this->l10n->t('Forgejo To-Dos');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int
	{
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string
	{
		return 'icon-forgejo';
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string
	{
		return $this->url->linkToRoute('settings.PersonalSettings.index', ['section' => 'connected-accounts']);
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void
	{
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_FORGEJO_URL) ?: Application::DEFAULT_FORGEJO_URL;
		$url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
		$oauthPossible = $clientID !== '' && $clientSecret !== '' && $url === $adminOauthUrl;
		$usePopup = $this->config->getAppValue(Application::APP_ID, 'use_popup', '0');

		$userConfig = [
			'oauth_is_possible' => $oauthPossible,
			'use_popup' => ($usePopup === '1'),
			'url' => $url,
			'client_id' => $clientID,
		];
		$this->initialStateService->provideInitialState('user-config', $userConfig);
		Util::addScript(Application::APP_ID, Application::APP_ID . '-dashboard');
		Util::addStyle(Application::APP_ID, 'dashboard');
	}
}
