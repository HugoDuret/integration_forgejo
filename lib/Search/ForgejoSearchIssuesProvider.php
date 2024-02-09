<?php

declare(strict_types=1);

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
namespace OCA\Forgejo\Search;

use OCA\Forgejo\Service\ForgejoAPIService;
use OCA\Forgejo\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Search\IProvider;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use OCP\Search\SearchResultEntry;

class ForgejoSearchIssuesProvider implements IProvider
{

	public function __construct(
		private IAppManager $appManager,
		private IL10N $l10n,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private ForgejoAPIService $service
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string
	{
		return 'forgejo-search-issues';
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string
	{
		return $this->l10n->t('Forgejo issues');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(string $route, array $routeParameters): int
	{
		if (strpos($route, Application::APP_ID . '.') === 0) {
			// Active app, prefer Forgejo results
			return -1;
		}

		return 20;
	}

	/**
	 * @inheritDoc
	 */
	public function search(IUser $user, ISearchQuery $query): SearchResult
	{
		if (!$this->appManager->isEnabledForUser(Application::APP_ID, $user)) {
			return SearchResult::complete($this->getName(), []);
		}

		$limit = $query->getLimit();
		$term = $query->getTerm();
		$offset = $query->getCursor();
		$offset = $offset ? intval($offset) : 0;

		$routeFrom = $query->getRoute();
		$requestedFromSmartPicker = $routeFrom === '' || $routeFrom === 'smart-picker';

		$searchIssuesEnabled = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'search_issues_enabled', '0') === '1';
		if (!$requestedFromSmartPicker && !$searchIssuesEnabled) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$accessToken = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'token');
		if ($accessToken === '') {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_FORGEJO_URL) ?: Application::DEFAULT_FORGEJO_URL;
		$url = $this->config->getUserValue($user->getUID(), Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;

		$issues = $this->service->searchIssues($user->getUID(), $term, $offset, $limit);
		if (isset($searchResult['error'])) {
			return SearchResult::paginated($this->getName(), [], 0);
		}

		$formattedResults = array_map(function (array $entry) use ($url): SearchResultEntry {
			$finalThumbnailUrl = $this->getThumbnailUrl($entry);
			return new SearchResultEntry(
				$finalThumbnailUrl,
				$this->getMainText($entry),
				$this->getSubline($entry, $url),
				$this->getLinkToForgejo($entry),
				$finalThumbnailUrl === '' ? 'icon-forgejo-search-fallback' : '',
				true
			);
		}, $issues);

		return SearchResult::paginated(
			$this->getName(),
			$formattedResults,
			$offset + $limit
		);
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getMainText(array $entry): string
	{
		$stateChar = $entry['state'] === 'closed' ? '❌' : '⋯';
		return $stateChar . ' ' . $entry['title'];
	}

	/**
	 * @param array $entry
	 * @param string $url
	 * @return string
	 */
	protected function getSubline(array $entry, string $url): string
	{
		$repoFullName = str_replace($url, '', $entry['web_url']);
		$repoFullName = preg_replace('/\/?-?\/issues\/.*/', '', $repoFullName);
		$repoFullName = preg_replace('/^\//', '', $repoFullName);
		//		$spl = explode('/', $repoFullName);
//		$owner = $spl[0];
//		$repo = $spl[1];
		$number = $entry['iid'];
		$typeChar = '🂠';
		$idChar = ' #';
		return $typeChar . ' ' . $idChar . $number . ' ' . $repoFullName;
	}

	/**
	 * @param array $entry
	 * @return string
	 */
	protected function getLinkToForgejo(array $entry): string
	{
		return $entry['web_url'] ?? '';
	}

	/**
	 * @param array $entry
	 * @param string $thumbnailUrl
	 * @return string
	 */
	protected function getThumbnailUrl(array $entry): string
	{
		$userId = $entry['author']['id'] ?? '';
		return $userId
			? $this->urlGenerator->linkToRoute('integration_forgejo.forgejoAPI.getUserAvatar', ['userId' => $userId])
			: '';
	}
}
