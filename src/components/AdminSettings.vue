<template>
  <div id="forgejo_prefs"
       class="section"
  >
    <h2>
      <ForgejoIcon class="icon" />
      {{ t('integration_forgejo', 'Forgejo integration') }}
    </h2>
    <p class="settings-hint">
      {{ t('integration_forgejo', 'If you want to allow your Nextcloud users to use OAuth to authenticate to a Forgejo instance of your choice, create an application in your Forgejo settings and set the ID and secret here.') }}
    </p>
    <p class="settings-hint">
      <InformationOutlineIcon :size="20"
                              class="icon"
      />
      {{ t('integration_forgejo', 'Make sure you set the "Redirect URI" to:') }}
    </p>
    <p class="settings-hint">
      <strong>{{ redirect_uri }}</strong>
    </p>
    <p class="settings-hint">
      {{ t('integration_forgejo', 'Give "read_user", "read_api" and "read_repository" permissions to the application. Optionally "api" instead.') }}
    </p>
    <p class="settings-hint">
      {{ t('integration_forgejo', 'Put the "Application ID" and "Application secret" below. Your Nextcloud users will then see a "Connect to Forgejo" button in their personal settings if they select the Forgejo instance defined here.') }}
    </p>
    <div id="forgejo-content">
      <div class="line">
        <label for="forgejo-oauth-instance">
          <EarthIcon :size="20"
                     class="icon"
          />
          {{ t('integration_forgejo', 'OAuth app instance address') }}
        </label>
        <input id="forgejo-oauth-instance"
               v-model="state.oauth_instance_url"
               type="text"
               :placeholder="t('integration_forgejo', 'Instance address')"
               @input="onInput"
        >
      </div>
      <div class="line">
        <label for="forgejo-client-id">
          <KeyIcon :size="20"
                   class="icon"
          />
          {{ t('integration_forgejo', 'Application ID') }}
        </label>
        <input id="forgejo-client-id"
               v-model="state.client_id"
               type="password"
               :readonly="readonly"
               :placeholder="t('integration_forgejo', 'ID of your Forgejo application')"
               @input="onInput"
               @focus="readonly = false"
        >
      </div>
      <div class="line">
        <label for="forgejo-client-secret">
          <KeyIcon :size="20"
                   class="icon"
          />
          {{ t('integration_forgejo', 'Application secret') }}
        </label>
        <input id="forgejo-client-secret"
               v-model="state.client_secret"
               type="password"
               :readonly="readonly"
               :placeholder="t('integration_forgejo', 'Client secret of your Forgejo application')"
               @focus="readonly = false"
               @input="onInput"
        >
      </div>
      <NcCheckboxRadioSwitch
        :checked="state.use_popup"
        @update:checked="onCheckboxChanged($event, 'use_popup')"
      >
        {{ t('integration_forgejo', 'Use a popup to authenticate') }}
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
import EarthIcon from 'vue-material-design-icons/Earth.vue'

import ForgejoIcon from './icons/ForgejoIcon.vue'

import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'

import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

export default {
	name: 'AdminSettings',

	components: {
		NcCheckboxRadioSwitch,
		ForgejoIcon,
		KeyIcon,
		EarthIcon,
		InformationOutlineIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_forgejo', 'admin-config'),
			// to prevent some browsers to fill fields with remembered passwords
			readonly: true,
			redirect_uri: window.location.protocol + '//' + window.location.host + generateUrl('/apps/integration_forgejo/oauth-redirect'),
		}
	},

	watch: {
	},

	mounted() {
	},

	methods: {
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
		},
		onInput() {
			delay(() => {
				this.saveOptions({
					client_id: this.state.client_id,
					client_secret: this.state.client_secret,
					oauth_instance_url: this.state.oauth_instance_url,
				})
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_forgejo/admin-config')
			axios.put(url, req).then((response) => {
				showSuccess(t('integration_forgejo', 'Forgejo admin options saved'))
			}).catch((error) => {
				showError(
					t('integration_forgejo', 'Failed to save Forgejo admin options')
					+ ': ' + (error.response?.request?.responseText ?? '')
				)
				console.debug(error)
			})
		},
	},
}
</script>

<style scoped lang="scss">
#forgejo_prefs {
	#forgejo-content{
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
