/* jshint esversion: 6 */

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
