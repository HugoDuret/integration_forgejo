/* jshint esversion: 6 */

/**
 * Nextcloud - forgejo
 *
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Hugo Duret <hugoduret@hotmail.fr>
 * @copyright Hugo Duret 2023
 */

import Vue from 'vue'
import './bootstrap.js'
import AdminSettings from './components/AdminSettings.vue'

// eslint-disable-next-line
'use strict'

// eslint-disable-next-line
new Vue({
	el: '#forgejo_prefs',
	render: h => h(AdminSettings),
})
