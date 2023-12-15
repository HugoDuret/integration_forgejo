<?php
/**
 * Nextcloud - forgejo
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Duret
 * @copyright Hugo Duret 2023
 */

namespace OCA\Forgejo\Service;

use DateInterval;
use Datetime;
use DateTimeImmutable;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use OCA\Forgejo\AppInfo\Application;
use OCP\Http\Client\IClient;
use OCP\IConfig;
use OCP\IL10N;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;

/**
 * Service to make requests to Forgejo v3 (JSON) API
 */
class ForgejoAPIService {

	private IClient $client;

	public function __construct (string                  $appName,
								 private LoggerInterface $logger,
								 private IL10N           $l10n,
								 private IConfig         $config,
								 IClientService          $clientService) {
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $userId
	 * @return array
	 * @throws Exception
	 */
	private function getMyProjectsInfo(string $userId): array {
		$params = [
			'membership' => 'true',
		];
		$projects = $this->request($userId, 'projects', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		$projectsInfo = [];
		foreach ($projects as $project) {
			$pid = $project['id'];
			$projectsInfo[$pid] = [
				'path_with_namespace' => $project['path_with_namespace'],
				'avatar_url' => $project['avatar_url'],
				'visibility' => $project['visibility'],
			];
		}
		return $projectsInfo;
	}

	/**
	 * @param int $offset
	 * @param int $limit
	 * @return array [perPage, page, leftPadding]
	 */
	public static function getForgejoPaginationValues(int $offset = 0, int $limit = 5): array {
		// compute pagination values
		// indexes offset => offset + limit
		if (($offset % $limit) === 0) {
			$perPage = $limit;
			// page number starts at 1
			$page = ($offset / $limit) + 1;
			return [$perPage, $page, 0];
		} else {
			$firstIndex = $offset;
			$lastIndex = $offset + $limit - 1;
			$perPage = $limit;
			// while there is no page that contains them'all
			while (intdiv($firstIndex, $perPage) !== intdiv($lastIndex, $perPage)) {
				$perPage++;
			}
			$page = intdiv($offset, $perPage) + 1;
			$leftPadding = $firstIndex % $perPage;

			return [$perPage, $page, $leftPadding];
		}
	}

	/**
	 * @param string $userId
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function searchRepositories(string $userId, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getForgejoPaginationValues($offset, $limit);
		$params = [
			'scope' => 'projects',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$projects = $this->request($userId, 'search', $params);
		if (isset($projects['error'])) {
			return $projects;
		}
		return array_slice($projects, $leftPadding, $limit);
	}

	/**
	 * @param string $userId
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 * @throws Exception
	 */
	public function searchIssues(string $userId, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getForgejoPaginationValues($offset, $limit);
		$params = [
			'scope' => 'issues',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$issues = $this->request($userId, 'search', $params);
		if (isset($issues['error'])) {
			return $issues;
		}
		return array_slice($issues, $leftPadding, $limit);
	}

	/**
	 * @param string $userId
	 * @param string $term
	 * @param int $offset
	 * @param int $limit
	 * @return array|string[]
	 * @throws PreConditionNotMetException
	 */
	public function searchMergeRequests(string $userId, string $term, int $offset = 0, int $limit = 5): array {
		[$perPage, $page, $leftPadding] = self::getForgejoPaginationValues($offset, $limit);
		$params = [
			'scope' => 'merge_requests',
			'search' => $term,
			'sort' => 'desc',
			'per_page' => $perPage,
			'page' => $page,
		];
		$mergeRequests = $this->request($userId, 'search', $params);
		if (isset($mergeRequests['error'])) {
			return $mergeRequests;
		}
		return array_slice($mergeRequests, $leftPadding, $limit);
	}
	public function getNotifications(string $userId, ?string $since = null): array {
		$result = $this->request($userId, 'notifications');

		if (isset($result['error'])) {
			return $result;
		}

		// filter results by date
		if (!is_null($since)) {
			// we get a full ISO date, the API only wants a day (non inclusive)
			$sinceDate = new DateTime($since);
			$sinceTimestamp = $sinceDate->getTimestamp();

			$result = array_filter($result, function($elem) use ($sinceTimestamp) {
				$date = new Datetime($elem['updated_at']);
				$ts = $date->getTimestamp();
				return $ts > $sinceTimestamp;
			});
		}

		// make sure it's an array and not a hastable
		$result = array_values($result);

		return $result;
	}

	public function getRepos(string $userId): array {
		$result = $this->request($userId, 'user/repos');

		if (isset($result['error'])) {
			return $result;
		}

		// make sure it's an array and not a hastable
		$result = array_values($result);

		return $result;
	}

	/**
	 * @param string|null $userId
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 * @throws PreConditionNotMetException
	 */
	public function request(?string $userId, string $endPoint, array $params = [], string $method = 'GET'): array {
		if ($userId !== null) {
			$this->checkTokenExpiration($userId);
		}
		$baseUrl = $this->getConnectedForgejoUrl($userId);
		try {
			$url = $baseUrl . '/api/v1/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Forgejo integration'
				],
			];

			// try anonymous request if no user (public page) or user not connected to a forgejo account
			if ($userId !== null) {
				$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
				if ($accessToken !== '') {
					$options['headers']['Authorization'] = 'Bearer ' . $accessToken;
				}
			}

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);

					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (ServerException | ClientException $e) {
			$this->logger->warning('Forgejo API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => 'Authentication failed'];
		} catch (ConnectException $e) {
			$this->logger->warning('Forgejo API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $userId
	 * @return void
	 * @throws PreConditionNotMetException
	 */
	private function checkTokenExpiration(string $userId): void {
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		$expireAt = $this->config->getUserValue($userId, Application::APP_ID, 'token_expires_at');
		if ($refreshToken !== '' && $expireAt !== '') {
			$nowTs = (new Datetime())->getTimestamp();
			$expireAt = (int) $expireAt;
			// if token expires in less than a minute or is already expired
			if ($nowTs > $expireAt - 60) {
				$this->refreshToken($userId);
			}
		}
	}

	/**
	 * @param string $userId
	 * @return bool
	 * @throws PreConditionNotMetException
	 */
	private function refreshToken(string $userId): bool {
		$baseUrl = $this->getConnectedForgejoUrl($userId);
		$clientID = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$redirect_uri = $this->config->getUserValue($userId, Application::APP_ID, 'redirect_uri');
		$refreshToken = $this->config->getUserValue($userId, Application::APP_ID, 'refresh_token');
		if (!$refreshToken) {
			$this->logger->error('No Forgejo refresh token found', ['app' => Application::APP_ID]);
			return false;
		}
		$result = $this->requestOAuthAccessToken($baseUrl, [
			'client_id' => $clientID,
			'client_secret' => $clientSecret,
			'grant_type' => 'refresh_token',
			'redirect_uri' => $redirect_uri,
			'refresh_token' => $refreshToken,
		], 'POST');
		if (isset($result['access_token'])) {
			$this->logger->info('Forgejo access token successfully refreshed', ['app' => Application::APP_ID]);
			$accessToken = $result['access_token'];
			$refreshToken = $result['refresh_token'];
			$this->config->setUserValue($userId, Application::APP_ID, 'token', $accessToken);
			$this->config->setUserValue($userId, Application::APP_ID, 'refresh_token', $refreshToken);
			if (isset($result['expires_in'])) {
				$nowTs = (new Datetime())->getTimestamp();
				$expiresAt = $nowTs + (int) $result['expires_in'];
				$this->config->setUserValue($userId, Application::APP_ID, 'token_expires_at', $expiresAt);
			}
			return true;
		} else {
			// impossible to refresh the token
			$this->logger->error(
				'Token is not valid anymore. Impossible to refresh it. '
					. $result['error'] . ' '
					. $result['error_description'] ?? '[no error description]',
				['app' => Application::APP_ID]
			);
			return false;
		}
	}

	/**
	 * @param string $userId
	 * @return string
	 */
	public function getConnectedForgejoUrl(string $userId): string {
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_FORGEJO_URL) ?: Application::DEFAULT_FORGEJO_URL;
		return $this->config->getUserValue($userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
	}

	/**
	 * @param string $userId
	 * @return array
	 */
	public function revokeOauthToken(string $userId): array {
		$forgejoUrl = $this->getConnectedForgejoUrl($userId);

		$accessToken = $this->config->getUserValue($userId, Application::APP_ID, 'token');
		$clientId = $this->config->getAppValue(Application::APP_ID, 'client_id');
		$clientSecret = $this->config->getAppValue(Application::APP_ID, 'client_secret');
		$endPoint = 'oauth/revoke';
		try {
			$url = $forgejoUrl . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Forgejo integration',
					'Content-Type' => 'application/json',
				],
				'body' => json_encode([
					'client_id' => $clientId,
					'client_secret' => $clientSecret,
					'token' => $accessToken,
				]),
			];

			$response = $this->client->post($url, $options);
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return [];
			}
		} catch (Exception $e) {
			$this->logger->warning('Forgejo API error : '.$e->getMessage(), ['app' => Application::APP_ID]);
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $url
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function requestOAuthAccessToken(string $url, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/oauth/token';
			$options = [
				'headers' => [
					'User-Agent'  => 'Nextcloud Forgejo integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					$paramsContent = http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('OAuth access token refused')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('Forgejo OAuth error : '.$e->getMessage(), array('app' => Application::APP_ID));
			return ['error' => $e->getMessage()];
		}
	}
}
