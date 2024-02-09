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

use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

use OCA\Forgejo\Service\ForgejoAPIService;
use OCA\Forgejo\AppInfo\Application;
use OCP\IURLGenerator;

class ForgejoAPIController extends Controller
{

	private string $accessToken;
	private string $forgejoUrl;

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IURLGenerator $urlGenerator,
		private ForgejoAPIService $forgejoAPIService,
		private ?string $userId
	) {
		parent::__construct($appName, $request);
		$this->accessToken = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
		$adminOauthUrl = $this->config->getAppValue(Application::APP_ID, 'oauth_instance_url', Application::DEFAULT_FORGEJO_URL) ?: Application::DEFAULT_FORGEJO_URL;
		$this->forgejoUrl = $this->config->getUserValue($this->userId, Application::APP_ID, 'url', $adminOauthUrl) ?: $adminOauthUrl;
	}

	/**
	 * get notifications list
	 * @NoAdminRequired
	 *
	 * @param string|null $since
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function getNotifications(?string $since = null): DataResponse
	{
		if ($this->accessToken === '') {
			return new DataResponse('', 400);
		}
		$result = $this->forgejoAPIService->getNotifications($this->userId, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}

	/**
	 * get repos list
	 * @NoAdminRequired
	 *
	 * @param string|null $since
	 * @return DataResponse
	 * @throws \Exception
	 */
	public function getRepos(?string $since = null): DataResponse
	{
		if ($this->accessToken === '') {
			return new DataResponse('', 400);
		}
		$result = $this->forgejoAPIService->getRepos($this->userId, $since);
		if (!isset($result['error'])) {
			$response = new DataResponse($result);
		} else {
			$response = new DataResponse($result, 401);
		}
		return $response;
	}
}
