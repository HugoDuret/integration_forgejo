<template>
  <div id="forgejo_prefs"
       class="section"
  >
    <h2>
      <ForgejoIcon class="icon" />
      {{ t('integration_forgejo', 'Forgejo integration') }}
    </h2>
    <p v-if="!showOAuth && !connected"
       class="settings-hint"
    >
      {{ t('integration_forgejo', 'When you create an access token yourself, give it at least "read_user", "read_api" and "read_repository" permissions. Optionally "api" instead.') }}
    </p>
    <div id="forgejo-content">
      <div class="line">
        <label for="forgejo-url">
          <EarthIcon :size="20"
                     class="icon"
          />
          {{ t('integration_forgejo', 'Forgejo instance address') }}
        </label>
        <input id="forgejo-url"
               v-model="state.url"
               type="text"
               :disabled="connected === true"
               :placeholder="t('integration_forgejo', 'Forgejo instance address')"
               @input="onInput"
        >
      </div>
      <div v-show="!showOAuth"
           class="line"
      >
        <label
          for="forgejo-token"
        >
          <KeyIcon :size="20"
                   class="icon"
          />
          {{ t('integration_forgejo', 'Personal access token') }}
        </label>
        <input
          id="forgejo-token"
          v-model="state.token"
          type="password"
          :disabled="connected === true"
          :placeholder="t('integration_forgejo', 'Forgejo personal access token')"
          @keyup.enter="onConnectClick"
        >
      </div>
      <NcButton v-if="!connected"
                id="forgejo-oauth"
                :disabled="loading === true || (!showOAuth && !state.token)"
                :class="{ loading }"
                @click="onConnectClick"
      >
        <template #icon>
          <OpenInNewIcon :size="20" />
        </template>
        {{ t('integration_forgejo', 'Connect to Forgejo') }}
      </NcButton>
      <div v-if="connected"
           class="line"
      >
        <label class="forgejo-connected">
          <CheckIcon :size="20"
                     class="icon"
          />
          {{ t('integration_forgejo', 'Connected as {user}', { user: connectedAs }) }}
        </label>
        <NcButton @click="onLogoutClick">
          <template #icon>
            <CloseIcon :size="20" />
          </template>
          {{ t('integration_forgejo', 'Disconnect from Forgejo') }}
        </NcButton>
        <span />
      </div>
      <br>
      <div v-if="connected"
           id="forgejo-search-block"
      >
        <NcCheckboxRadioSwitch
          :checked="state.search_enabled"
          @update:checked="onCheckboxChanged($event, 'search_enabled')"
        >
          {{ t('integration_forgejo', 'Enable searching for repositories') }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :checked="state.search_issues_enabled"
          @update:checked="onCheckboxChanged($event, 'search_issues_enabled')"
        >
          {{ t('integration_forgejo', 'Enable searching for issues') }}
        </NcCheckboxRadioSwitch>
        <NcCheckboxRadioSwitch
          :checked="state.search_mrs_enabled"
          @update:checked="onCheckboxChanged($event, 'search_mrs_enabled')"
        >
          {{ t('integration_forgejo', 'Enable searching for merge requests') }}
        </NcCheckboxRadioSwitch>
        <br>
        <p v-if="state.search_enabled || state.search_issues_enabled"
           class="settings-hint"
        >
          <InformationOutlineIcon :size="20"
                                  class="icon"
          />
          {{ t('integration_forgejo', 'Warning, everything you type in the search bar will be sent to Forgejo.') }}
        </p>
      </div>
      <NcCheckboxRadioSwitch
        :checked="state.navigation_enabled"
        @update:checked="onCheckboxChanged($event, 'navigation_enabled')"
      >
        {{ t('integration_forgejo', 'Enable navigation link') }}
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch
        :checked="state.link_preview_enabled"
        @update:checked="onCheckboxChanged($event, 'link_preview_enabled')"
      >
        {{ t('integration_forgejo', 'Enable Forgejo link previews') }}
      </NcCheckboxRadioSwitch>
    </div>
  </div>
</template>

<script>
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import KeyIcon from 'vue-material-design-icons/Key.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import EarthIcon from 'vue-material-design-icons/Earth.vue'

import ForgejoIcon from './icons/ForgejoIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, oauthConnect } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'PersonalSettings',

	components: {
		ForgejoIcon,
		NcCheckboxRadioSwitch,
		NcButton,
		OpenInNewIcon,
		EarthIcon,
		CheckIcon,
		CloseIcon,
		KeyIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_forgejo', 'user-config'),
			loading: false,
		}
	},

	computed: {
		showOAuth() {
			return (this.state.url === this.state.oauth_instance_url) && this.state.client_id && this.state.client_secret
		},
		connected() {
			return !!this.state.token
				&& !!this.state.url
				&& !!this.state.user_name
		},
		connectedAs() {
			return this.state.user_displayname
				? this.state.user_displayname + ' (@' + this.state.user_name + ')'
				: '@' + this.state.user_name
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.slice(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const token = urlParams.get('forgejoToken')
		if (token === 'success') {
			showSuccess(t('integration_forgejo', 'Successfully connected to Forgejo!'))
		} else if (token === 'error') {
			showError(t('integration_forgejo', 'Error connecting to Forgejo:') + ' ' + urlParams.get('message'))
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions({ token: '' })
		},
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			this.loading = true
			delay(() => {
				this.saveOptions({ url: this.state.url })
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_forgejo/config')
			axios.put(url, req).then((response) => {
				if (response.data.user_name !== undefined) {
					this.state.user_name = response.data.user_name
					this.state.user_displayname = response.data.user_displayname
					if (this.state.token && response.data.user_name === '') {
						showError(t('integration_forgejo', 'Incorrect access token'))
					} else if (response.data.user_name) {
						showSuccess(t('integration_forgejo', 'Successfully connected to Forgejo!'))
					}
				} else {
					showSuccess(t('integration_forgejo', 'Forgejo options saved'))
				}
			}).catch((error) => {
				showError(
					t('integration_forgejo', 'Failed to save Forgejo options')
					+ ': ' + (error.response?.data?.error ?? '')
				)
				console.debug(error)
			}).then(() => {
				this.loading = false
			})
		},
		onConnectClick() {
			if (this.showOAuth) {
				this.connectWithOauth()
			} else {
				this.connectWithToken()
			}
		},
		connectWithToken() {
			this.loading = true
			this.saveOptions({
				token: this.state.token,
				url: this.state.url,
			})
		},
		connectWithOauth() {
			if (this.state.use_popup) {
				oauthConnect(this.state.url, this.state.client_id, null, true)
					.then((data) => {
						this.state.token = 'dummyToken'
						this.state.user_name = data.userName
						this.state.user_displayname = data.userDisplayName
					})
			} else {
				oauthConnect(this.state.url, this.state.client_id, 'settings')
			}
		},
	},
}
</script>

<style scoped lang="scss">
#forgejo_prefs {
	#forgejo-content {
		margin-left: 40px;
	}
	h2,
	.line,
	.settings-hint {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	h2 .icon {
		margin-right: 8px;
	}

	.line {
		> label {
			width: 300px;
			display: flex;
			align-items: center;
		}
		> input {
			width: 300px;
		}
	}
}
</style>
