<template>
  <DashboardWidget :items="items"
                   :show-more-url="showMoreUrl"
                   :show-more-text="title"
                   :loading="state === 'loading'"
                   :item-menu="itemMenu"
  >
    <template #empty-content>
      <NcEmptyContent v-if="emptyContentMessage"
                      :title="emptyContentMessage"
      >
        <template #icon>
          <component :is="emptyContentIcon" />
        </template>
        <template #action>
          <div v-if="state === 'no-token' || state === 'error'"
               class="connect-button"
          >
            <a v-if="!initialState.oauth_is_possible"
               :href="settingsUrl"
            >
              <NcButton>
                <template #icon>
                  <LoginVariantIcon />
                </template>
                {{ t('integration_forgejo', 'Connect to Forgejo') }}
              </NcButton>
            </a>
            <NcButton v-else
                      @click="onOauthClick"
            >
              <template #icon>
                <LoginVariantIcon />
              </template>
              {{ t('integration_forgejo', 'Connect to {url}', { url: forgejoUrl }) }}
            </NcButton>
          </div>
        </template>
      </NcEmptyContent>
    </template>
  </DashboardWidget>
</template>

<script>
import LoginVariantIcon from 'vue-material-design-icons/LoginVariant.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'

import ForgejoIcon from '../components/icons/ForgejoIcon.vue'

import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import { loadState } from '@nextcloud/initial-state'
import moment from '@nextcloud/moment'

import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { oauthConnect, oauthConnectConfirmDialog } from '../utils.js'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
		NcEmptyContent,
		NcButton,
		LoginVariantIcon,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			notifications: [],
			repos: [],
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			itemMenu: {
				markDone: {
					text: t('integration_forgejo', 'Mark as done'),
					icon: 'icon-checkmark',
				},
			},
			initialState: loadState('integration_forgejo', 'user-config'),
			windowVisibility: true,
		}
	},

	computed: {
		forgejoUrl() {
			return this.initialState?.url?.replace(/\/+$/, '')
		},
		showMoreUrl() {
			return this.forgejoUrl + '/notifications'
		},
		items() {
			const notifications = this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUsername: this.getRepositoryName(n),
					avatarIsNoUser: true,
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
			const repos = this.repos.map((r) => {
				return {
					id: r.id,
					targetUrl: r.original_url,
					avatarUsername: r.name,
					avatarIsNoUser: true,
					overlayIconUrl: r.avatar_url,
					mainText: r.full_name,
					subText: r.description,
				}
			})
			return notifications.concat(repos);
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].updated_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_forgejo', 'No Forgejo account connected')
			} else if (this.state === 'error') {
				return t('integration_forgejo', 'Error connecting to Forgejo')
			} else if (this.state === 'ok') {
				return t('integration_forgejo', 'No Forgejo notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return ForgejoIcon
			} else if (this.state === 'error') {
				return CloseIcon
			} else if (this.state === 'ok') {
				return CheckIcon
			}
			return CheckIcon
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.launchLoop()
			} else {
				this.stopLoop()
			}
		},
	},

	beforeUnmount() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.launchLoop()
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	mounted() {
	},

	methods: {
		onOauthClick() {
			oauthConnectConfirmDialog(this.forgejoUrl).then((result) => {
				if (result) {
					if (this.initialState.use_popup) {
						this.state = 'loading'
						oauthConnect(this.forgejoUrl, this.initialState.client_id, null, true)
							.then((data) => {
								this.stopLoop()
								this.launchLoop()
							})
					} else {
						oauthConnect(this.forgejoUrl, this.initialState.client_id, 'dashboard')
					}
				}
			})
		},
		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},
		stopLoop() {
			clearInterval(this.loop)
		},
		async launchLoop() {
			this.fetchNotifications()
			this.fetchRepos()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_forgejo/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_forgejo', 'Failed to get Forgejo notifications'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].updated_at)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		fetchRepos() {
			axios.get(generateUrl('/apps/integration_forgejo/user/repos')).then((response) => {
				this.processRepos(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_forgejo', 'Failed to get Forgejo repos')) // TODO: create label
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processRepos(newRepos) {
			this.repos = newRepos
		},
		filter(notifications) {
			return notifications.filter((n) => {
				return n.action_name !== 'marked'
			})
		},
		getNotificationTarget(n) {
			return n.target_url
		},
		getUniqueKey(n) {
			return n.id + ':' + n.updated_at
		},
		getAuthorFullName(n) {
			return n.author.name
				? (n.author.name + ' (@' + n.author.username + ')')
				: n.author.username
		},
		getRepositoryName(n) {
			return n.project.path
				? n.project.path
				: ''
		},
		getNotificationProjectName(n) {
			return n.project.path_with_namespace
		},
		getNotificationContent(n) {
			if (n.action_name === 'mentioned') {
				return t('integration_forgejo', 'You were mentioned')
			} else if (n.action_name === 'approval_required') {
				return t('integration_forgejo', 'Your approval is required')
			} else if (n.action_name === 'assigned') {
				return t('integration_forgejo', 'You were assigned')
			} else if (n.action_name === 'build_failed') {
				return t('integration_forgejo', 'A build has failed')
			} else if (n.action_name === 'marked') {
				return t('integration_forgejo', 'Marked')
			} else if (n.action_name === 'directly_addressed') {
				return t('integration_forgejo', 'You were directly addressed')
			}
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.target_type === 'MergeRequest') {
				return imagePath('integration_forgejo', 'merge_request.svg')
			} else if (n.target_type === 'Issue') {
				return imagePath('integration_forgejo', 'issues.svg')
			}
			return imagePath('integration_forgejo', 'sound-border.svg')
		},
		getNotificationActionChar(n) {
			if (['Issue', 'MergeRequest'].includes(n.target_type)) {
				if (['approval_required', 'assigned'].includes(n.action_name)) {
					return '👁'
				} else if (['directly_addressed', 'mentioned'].includes(n.action_name)) {
					return '🗨'
				} else if (n.action_name === 'marked') {
					return '✅'
				} else if (['build_failed', 'unmergeable'].includes(n.action_name)) {
					return '❎'
				}
			}
			return ''
		},
		getSubline(n) {
			return this.getNotificationActionChar(n) + ' ' + n.project.path_with_namespace + this.getTargetIdentifier(n)
		},
		getTargetContent(n) {
			return n.body
		},
		getTargetTitle(n) {
			return n.target.title
		},
		getProjectPath(n) {
			return n.project.path_with_namespace
		},
		getTargetIdentifier(n) {
			if (n.target_type === 'MergeRequest') {
				return '!' + n.target.iid
			} else if (n.target_type === 'Issue') {
				return '#' + n.target.iid
			}
			return ''
		},
		getFormattedDate(n) {
			return moment(n.updated_at).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
