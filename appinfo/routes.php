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

return [
	'routes' => [
		['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
		['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
		['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
		['name' => 'config#popupSuccessPage', 'url' => '/popup-success', 'verb' => 'GET'],

		['name' => 'forgejoAPI#getRepos', 'url' => '/user/repos', 'verb' => 'GET'],
		['name' => 'forgejoAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
	]
];
