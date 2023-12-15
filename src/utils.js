import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'

let mytimer = 0
export function delay(callback, ms) {
	return function() {
		const context = this
		const args = arguments
		clearTimeout(mytimer)
		mytimer = setTimeout(function() {
			callback.apply(context, args)
		}, ms || 0)
	}
}

export function oauthConnect(forgejoUrl, clientId, oauthOrigin, usePopup = false) {
	const redirectUri = window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_forgejo/oauth-redirect')

	const oauthState = Math.random().toString(36).substring(3)
	const requestUrl = forgejoUrl + '/oauth/authorize'
		+ '?client_id=' + encodeURIComponent(clientId)
		+ '&redirect_uri=' + encodeURIComponent(redirectUri)
		+ '&response_type=code'
		+ '&state=' + encodeURIComponent(oauthState)
		+ '&scope=' + encodeURIComponent('read_user read_api read_repository')

	const req = {
		values: {
			oauth_state: oauthState,
			redirect_uri: redirectUri,
			oauth_origin: usePopup ? undefined : oauthOrigin,
		},
	}
	const url = generateUrl('/apps/integration_forgejo/config')
	return new Promise((resolve, reject) => {
		axios.put(url, req).then((response) => {
			if (usePopup) {
				const ssoWindow = window.open(
					requestUrl,
					t('integration_forgejo', 'Connect to Forgejo'),
					'toolbar=no, menubar=no, width=600, height=700')
				ssoWindow.focus()
				window.addEventListener('message', (event) => {
					console.debug('Child window message received', event)
					resolve(event.data)
				})
			} else {
				window.location.replace(requestUrl)
			}
		}).catch((error) => {
			showError(
				t('integration_forgejo', 'Failed to save Forgejo OAuth state')
				+ ': ' + (error.response?.request?.responseText ?? '')
			)
			console.error(error)
		})
	})
}

export function oauthConnectConfirmDialog(forgejoUrl) {
	return new Promise((resolve, reject) => {
		const settingsLink = generateUrl('/settings/user/connected-accounts')
		const linkText = t('integration_forgejo', 'Connected accounts')
		const settingsHtmlLink = `<a href="${settingsLink}" class="external">${linkText}</a>`
		OC.dialogs.message(
			t('integration_forgejo', 'You need to connect before using the Forgejo integration.')
			+ '<br><br>'
			+ t('integration_forgejo', 'Do you want to connect to {forgejoUrl}?', { forgejoUrl })
			+ '<br><br>'
			+ t(
				'integration_forgejo',
				'You can choose another Forgejo server in the {settingsHtmlLink} section of your personal settings.',
				{ settingsHtmlLink },
				null,
				{ escape: false }
			),
			t('integration_forgejo', 'Connect to Forgejo'),
			'none',
			{
				type: OC.dialogs.YES_NO_BUTTONS,
				confirm: t('integration_forgejo', 'Connect'),
				confirmClasses: 'success',
				cancel: t('integration_forgejo', 'Cancel'),
			},
			(result) => {
				resolve(result)
			},
			true,
			true,
		)
	})
}

export function hexToRgb(hex) {
	const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex)
	return result
		? {
			r: parseInt(result[1], 16),
			g: parseInt(result[2], 16),
			b: parseInt(result[3], 16),
		}
		: null
}
