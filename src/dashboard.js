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
import Dashboard from './views/Dashboard.vue'

document.addEventListener('DOMContentLoaded', function() {
	
	OCA.Dashboard.register('forgejo_todos', (el, { widget }) => {
		const View = Vue.extend(Dashboard)
		new View({
			propsData: { title: widget.title },
		}).$mount(el)
	})

})
