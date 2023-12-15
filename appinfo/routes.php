<?php
/**
 * Nextcloud - Forgejo
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Duret <hugoduret@hotmail.fr>
 * @copyright Hugo Duret 2023
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
