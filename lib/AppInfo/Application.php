<?php
/**
 * Nextcloud - Forgejo
 *
 *
 * @author Hugo Duret <hugoduret@hotmail.fr>
 * @copyright Hugo Duret 2023
 */

namespace OCA\Forgejo\AppInfo;

use Closure;
use OCA\Forgejo\Listener\ForgejoReferenceListener;
use OCA\Forgejo\Reference\ForgejoReferenceProvider;
use OCP\Collaboration\Reference\RenderReferenceEvent;
use OCP\IConfig;
use OCP\IL10N;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;

use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Forgejo\Dashboard\ForgejoWidget;
use OCA\Forgejo\Search\ForgejoSearchIssuesProvider;
use OCA\Forgejo\Search\ForgejoSearchMergeRequestsProvider;
use OCA\Forgejo\Search\ForgejoSearchReposProvider;
use OCP\Util;

class Application extends App implements IBootstrap {
	public const APP_ID = 'integration_forgejo';
	public const DEFAULT_FORGEJO_URL = 'https://next.forgejo.org';

	private IConfig $config;

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(ForgejoWidget::class);
		$context->registerSearchProvider(ForgejoSearchIssuesProvider::class);
		$context->registerSearchProvider(ForgejoSearchMergeRequestsProvider::class);
		$context->registerSearchProvider(ForgejoSearchReposProvider::class);

		$context->registerReferenceProvider(ForgejoReferenceProvider::class);
		$context->registerEventListener(RenderReferenceEvent::class, ForgejoReferenceListener::class);
	}

	public function boot(IBootContext $context): void {
		$context->injectFn(Closure::fromCallable([$this, 'registerNavigation']));
		Util::addStyle(self::APP_ID, 'forgejo-search');
	}

	public function registerNavigation(IUserSession $userSession): void {
		$user = $userSession->getUser();
		if ($user !== null) {
			$userId = $user->getUID();
			$container = $this->getContainer();

			if ($this->config->getUserValue($userId, self::APP_ID, 'navigation_enabled', '0') === '1') {
				$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', self::DEFAULT_FORGEJO_URL) ?: self::DEFAULT_FORGEJO_URL;
				$forgejoUrl = $this->config->getUserValue($userId, self::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
				$container->get(INavigationManager::class)->add(function () use ($container, $forgejoUrl) {
					$urlGenerator = $container->get(IURLGenerator::class);
					$l10n = $container->get(IL10N::class);
					return [
						'id' => self::APP_ID,
						'order' => 10,
						'href' => $forgejoUrl,
						'target' => '_blank',
						'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
						'name' => $l10n->t('Forgejo'),
					];
				});
			}
		}
	}
}

