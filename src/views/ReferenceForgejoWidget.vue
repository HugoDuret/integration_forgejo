<!--
 - @copyright hugo.duret@cea.fr 2024
 -
 - Use of this source code is governed by an MIT-style
 - license that can be found in the LICENSE file or at
 - https://opensource.org/licenses/MIT.
 -  
 - Contributors:
 -  @author Hugo Duret  hugo.duret@cea.fr - Initial implementation
 -
 -/
  -->

<template>
  <div class="forgejo-reference">
    <div v-if="isError">
      <h3>
        <ForgejoIcon :size="20"
                     class="icon"
        />
        <span>{{ t('integration_forgejo', 'Forgejo API error') }}</span>
      </h3>
      <p v-if="richObject.body?.message"
         class="widget-error"
      >
        {{ richObject.body?.message }}
      </p>
      <p v-else
         class="widget-error"
      >
        {{ t('integration_forgejo', 'Unknown error') }}
      </p>
      <a :href="settingsUrl"
         class="settings-link external"
         target="_blank"
      >
        <OpenInNewIcon :size="20"
                       class="icon"
        />
        {{ t('integration_forgejo', 'Forgejo connected accounts settings') }}
      </a>
    </div>
    <div v-if="isIssue || isPr"
         class="issue-pr-wrapper"
    >
      <div class="issue-pr-info">
        <div class="line">
          <div class="title">
            <a :href="richObject.web_url"
               class="issue-pr-link"
               target="_blank"
            >
              <strong>
                {{ richObject.title }}
              </strong>
            </a>
          </div>
        </div>
        <div class="sub-text">
          <component :is="iconComponent"
                     v-tooltip.top="{ content: stateTooltip }"
                     :size="16"
                     class="icon"
                     :fill-color="iconColor"
          />
          <span>
            <a :href="repoUrl"
               class="slug-link"
               target="_blank"
            >
              {{ slug }}
            </a>
            {{ forgejoId }}
          </span>
          <a :href="richObject.author.web_url"
             target="_blank"
             class="author-link"
          >
            {{ t('integration_forgejo', 'by {creator}', { creator: richObject.author.username }) }}
          </a>
          <span
            v-tooltip.top="{ content: createdAtFormatted }"
            class="date-with-tooltip"
          >
            {{ createdAtText }}
          </span>
          <span v-if="isPr">
            {{ prSubText }}
          </span>
          <a v-if="richObject.milestone"
             v-tooltip.top="{ content: richObject.milestone.description }"
             :href="richObject.milestone.web_url"
             target="_blank"
             class="milestone"
          >
            <MilestoneIcon :size="16"
                           class="icon"
            />
            {{ richObject.milestone.title }}
          </a>
          <div v-for="label in labels"
               :key="label.id"
               v-tooltip.top="{ content: label.description }"
               class="label"
               :style="{
                 background: label.color,
                 color: label.text_color,
               }"
          >
            {{ label.name }}
          </div>
        </div>
      </div>
      <div class="right-content">
        <div>
          <span v-if="richObject.state === 'closed'"
                class="closed-prefix"
          >
            {{ t('integration_forgejo', 'CLOSED') }}
          </span>
          <span v-if="richObject.state === 'merged'"
                class="closed-prefix"
          >
            {{ t('integration_forgejo', 'MERGED') }}
          </span>
          <div v-if="richObject.assignees.length > 0"
               class="assignee-avatars"
          >
            <NcAvatar v-for="assignee in richObject.assignees"
                      :key="assignee.username"
                      :tooltip-message="getAssigneeTooltip(assignee)"
                      :is-no-user="true"
                      :size="20"
                      :url="getAssigneeAvatarUrl(assignee)"
            />
          </div>
          <div v-if="richObject.upvotes > 0"
               v-tooltip.top="{ content: t('integration_forgejo', 'Upvotes') }"
               class="comments-count"
          >
            <UpVoteIcon :size="16"
                        class="icon"
            />
            {{ richObject.upvotes }}
          </div>
          <div v-if="richObject.downvotes > 0"
               v-tooltip.top="{ content: t('integration_forgejo', 'Downvotes') }"
               class="comments-count"
          >
            <DownVoteIcon :size="16"
                          class="icon"
            />
            {{ richObject.downvotes }}
          </div>
          <div v-tooltip.top="{ content: t('integration_forgejo', 'Comments') }"
               class="comments-count"
          >
            <CommentIcon :size="16"
                         class="icon"
            />
            {{ richObject.user_notes_count }}
          </div>
        </div>
        <div v-if="richObject.closed_at"
             v-tooltip.top="{ content: closedAtFormatted }"
             class="closed-at date-with-tooltip"
        >
          {{ closedAtText }}
        </div>
        <div v-else-if="richObject.updated_at"
             v-tooltip.top="{ content: updatedAtFormatted }"
             class="updated-at date-with-tooltip"
        >
          {{ updatedAtText }}
        </div>
      </div>
    </div>
    <div v-if="richObject.forgejo_comment"
         class="comment"
    >
      <div class="comment--author">
        <NcAvatar
          class="comment--author--avatar"
          :tooltip-message="commentAuthorTooltip"
          :is-no-user="true"
          :url="commentAuthorAvatarUrl"
        />
        <span class="comment--author--bubble-tip" />
        <span class="comment--author--bubble">
          <div class="comment--author--bubble--header">
            <a :href="richObject.forgejo_comment.author.web_url"
               target="_blank"
               class="author-link"
            >
              <strong class="comment-author-display-name">{{ richObject.forgejo_comment.author.name }}</strong>
              @{{ richObject.forgejo_comment.author.username }}
            </a>
            &nbsp;·&nbsp;
            <span
              v-tooltip.top="{ content: commentUpdatedAtTooltip }"
              class="date-with-tooltip"
            >
              {{ commentUpdatedAtText }}
            </span>
            <div class="spacer" />
            <div v-if="richObject.forgejo_comment.author.username === richObject.author.username"
                 class="label"
            >
              {{ t('integration_forgejo', 'Author') }}
            </div>
            <div v-if="richObject.forgejo_comment.author.username === richObject.forgejo_project_owner_username"
                 class="label"
            >
              {{ t('integration_forgejo', 'Owner') }}
            </div>
          </div>
          <div :class="{
            'comment--author--bubble--content': true,
            'short-comment': shortComment,
          }"
          >
            <NcRichText
              v-tooltip.top="{ content: shortComment ? t('integration_forgejo', 'Click to expand comment') : undefined }"
              :text="richObject.forgejo_comment.body"
              :use-markdown="true"
              @click.native="shortComment = !shortComment"
            />
          </div>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'

import ForgejoIcon from '../components/icons/ForgejoIcon.vue'
import IssueIcon from '../components/icons/IssueIcon.vue'
import CommentIcon from '../components/icons/CommentIcon.vue'
import MilestoneIcon from '../components/icons/MilestoneIcon.vue'
import MergeRequestIcon from '../components/icons/MergeRequestIcon.vue'
import UpVoteIcon from '../components/icons/UpVoteIcon.vue'
import DownVoteIcon from '../components/icons/DownVoteIcon.vue'
import ClosedIssueIcon from '../components/icons/ClosedIssueIcon.vue'
import ClosedMergeRequestIcon from '../components/icons/ClosedMergeRequestIcon.vue'
import MergedMergeRequestIcon from '../components/icons/MergedMergeRequestIcon.vue'

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'

import { NcRichText } from '@nextcloud/vue/dist/Components/NcRichText.js'
import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'
import Vue from 'vue'
Vue.directive('tooltip', Tooltip)

export default {
	name: 'ReferenceForgejoWidget',

	components: {
		NcRichText,
		DownVoteIcon,
		UpVoteIcon,
		ForgejoIcon,
		MilestoneIcon,
		MergeRequestIcon,
		// CommentReactions,
		NcAvatar,
		CommentIcon,
		OpenInNewIcon,
	},

	props: {
		richObjectType: {
			type: String,
			default: '',
		},
		richObject: {
			type: Object,
			default: null,
		},
		accessible: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			settingsUrl: generateUrl('/settings/user/connected-accounts#forgejo_prefs'),
			shortComment: true,
		}
	},

	computed: {
		isError() {
			return ['issue-error', 'pr-error'].includes(this.richObject.forgejo_type)
		},
		isIssue() {
			return this.richObject.forgejo_type === 'issue'
		},
		isPr() {
			return this.richObject.forgejo_type === 'pr'
		},
		slug() {
			return this.richObject.forgejo_repo_owner + '/' + this.richObject.forgejo_repo
		},
		repoUrl() {
			return this.richObject.forgejo_url + '/' + this.slug
		},
		forgejoId() {
			if (this.isIssue) {
				return '#' + this.richObject.forgejo_issue_id
			} else if (this.isPr) {
				return '!' + this.richObject.forgejo_pr_id
			}
			return ''
		},
		labels() {
			return this.richObject.labels?.map(l => {
				return this.richObject.forgejo_project_labels.find(sl => sl.name === l)
			})
		},
		iconComponent() {
			if (this.isIssue) {
				if (this.richObject.state === 'opened') {
					return IssueIcon
				} else if (this.richObject.state === 'closed') {
					return ClosedIssueIcon
				}
			} else if (this.isPr) {
				if (this.richObject.state === 'opened') {
					return MergeRequestIcon
				} else if (this.richObject.state === 'closed') {
					return ClosedMergeRequestIcon
				} else if (this.richObject.state === 'merged') {
					return MergedMergeRequestIcon
				}
			}
			return IssueIcon
		},
		iconColor() {
			if (this.richObject.state === 'opened') {
				return '#108548'
			} else if (this.richObject.state === 'closed') {
				if (this.isIssue) {
					return '#1f75cb'
				} else {
					return '#ae1800'
				}
			} else if (this.richObject.state === 'merged') {
				return '#1f75cb'
			}
			return '#8b949e'
		},
		stateTooltip() {
			if (this.isIssue) {
				if (this.richObject.state === 'opened') {
					return t('integration_forgejo', 'Open issue')
				} else if (this.richObject.state === 'closed') {
					return t('integration_forgejo', 'Closed issue')
				}
			} else if (this.isPr) {
				if (this.richObject.state === 'opened') {
					return t('integration_forgejo', 'Open merge request')
				} else if (this.richObject.state === 'closed') {
					return t('integration_forgejo', 'Closed merge request')
				} else if (this.richObject.state === 'merged') {
					return t('integration_forgejo', 'Merged merge request')
				}
			}
			return t('integration_forgejo', 'Unknown state')
		},
		prSubText() {
			return this.richObject.reviewers?.length > 0 ? ' • ' + t('integration_forgejo', 'Review requested') : ''
		},
		createdAtFormatted() {
			return moment(this.richObject.created_at).format('LLL')
		},
		closedAtFormatted() {
			return moment(this.richObject.closed_at).format('LLL')
		},
		updatedAtFormatted() {
			return moment(this.richObject.updated_at).format('LLL')
		},
		createdAtText() {
			return t('integration_forgejo', 'created {relativeDate}', { relativeDate: moment(this.richObject.created_at).fromNow() })
		},
		closedAtText() {
			return t('integration_forgejo', 'closed {relativeDate}', { relativeDate: moment(this.richObject.closed_at).fromNow() })
		},
		updatedAtText() {
			return t('integration_forgejo', 'updated {relativeDate}', { relativeDate: moment(this.richObject.updated_at).fromNow() })
		},
		commentUpdatedAtText() {
			return moment(this.richObject.forgejo_comment.updated_at).fromNow()
		},
		commentUpdatedAtTooltip() {
			return moment(this.richObject.forgejo_comment.updated_at).format('LLL')
		},
		commentAuthorAvatarUrl() {
			const userId = this.richObject.forgejo_comment?.author?.id ?? ''
			return generateUrl('/apps/integration_forgejo/avatar/user/{userId}', { userId })
		},
		commentAuthorTooltip() {
			return t('integration_forgejo', 'Comment from @{username}', { username: this.richObject.forgejo_comment?.author?.username ?? '' })
		},
	},

	methods: {
		getAssigneeAvatarUrl(assignee) {
			const userId = assignee.id ?? ''
			return generateUrl('/apps/integration_forgejo/avatar/user/{userId}', { userId })
		},
		getAssigneeTooltip(assignee) {
			return t('integration_forgejo', 'Assigned to {username}', { username: assignee.username })
		},
	},
}
</script>

<style scoped lang="scss">
.forgejo-reference {
	width: 100%;
	white-space: normal;
	padding: 12px;

	a {
		padding: 0 !important;
		color: var(--color-main-text) !important;
		text-decoration: unset !important;
	}

	h3 {
		display: flex;
		align-items: center;
		font-weight: bold;
		.icon {
			margin-right: 8px;
		}
	}

	.issue-pr-wrapper {
		width: 100%;
		display: flex;
		flex-wrap: wrap;
		align-items: start;

		.issue-pr-info {
			display: flex;
			flex: 1 1 300px;
			max-width: fit-content;
			align-items: center;
			flex-wrap: wrap;
		}

		.assignee-avatars {
			display: flex;
			align-items: center;
			margin-left: 8px;
		}

		.title {
			display: flex;
			align-items: center;
			flex-wrap: wrap;
			> * {
				margin-bottom: 2px;
			}
			.issue-pr-link {
				margin-right: 8px;
			}
		}

		.line {
			display: flex;
			align-items: center;

			> .icon {
				margin: 0 16px 0 8px;
			}
		}

		.sub-text {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			color: var(--color-text-maxcontrast);
			> * {
				margin-right: 4px;
			}

			.icon {
				margin-right: 4px;
			}
			.milestone {
				display: flex;
				align-items: center;
			}
		}

		.closed-at,
		.updated-at {
			color: var(--color-text-maxcontrast);
			white-space: nowrap;
		}

		.right-content {
			display: flex;
			flex-direction: column;
			align-items: end;
			text-align: right;

			> * {
				display: flex;
				align-items: center;
			}

			.comments-count {
				display: flex;
				align-items: center;
				margin-left: 8px;
				color: var(--color-text-maxcontrast);
				white-space: nowrap;
				.icon {
					margin-right: 4px;
				}
			}
		}
	}

	.comment {
		margin-top: 8px;
		display: flex;
		flex-direction: column;
		align-items: start;
		&--author {
			display: flex;
			align-items: start;
			width: 100%;

			&--avatar {
				margin-top: 4px;
			}

			&--bubble {
				display: flex;
				flex-direction: column;
				width: 100%;
				padding: 4px 8px;
				border: 1px solid var(--color-border-dark);
				border-radius: var(--border-radius);

				&--header {
					display: flex;
					align-items: center;
					margin-bottom: 6px;
					color: var(--color-text-maxcontrast);
					.comment-author-display-name {
						color: var(--color-main-text);
					}
				}

				&--content {
					cursor: pointer;
					max-height: 250px;
					overflow: scroll;
					&.short-comment {
						max-height: 25px;
						overflow: hidden;
					}
				}
			}
			&--bubble-tip {
				margin-left: 15px;
				position: relative;
				top: 20px;
				&:before {
					content: '';
					width: 0px;
					height: 0px;
					position: absolute;
					border-left: 8px solid transparent;
					border-right: 8px solid var(--color-border-dark);
					border-top: 8px solid transparent;
					border-bottom: 8px solid transparent;
					left: -15px;
					top: -8px;
				}

				&:after {
					content: '';
					width: 0px;
					height: 0px;
					position: absolute;
					border-left: 8px solid transparent;
					border-right: 8px solid var(--color-main-background);
					border-top: 8px solid transparent;
					border-bottom: 8px solid transparent;
					left: -14px;
					top: -8px;
				}
				.message-body:hover &:after {
					border-right: 8px solid var(--color-background-hover);
				}
			}
		}
	}

	.label {
		display: flex;
		align-items: center;
		height: 20px;
		margin-right: 4px;
		// border: 1px solid var(--color-border-dark);
		padding: 0 7px;
		border-radius: var(--border-radius-pill);
		font-size: 12px;
	}

	.milestone,
	::v-deep .author-link,
	.slug-link {
		color: inherit !important;
	}

	.date-with-tooltip,
	.milestone,
	::v-deep .author-link,
	.author-link:hover .comment-author-display-name,
	.slug-link,
	.issue-pr-link {
		&:hover {
			color: #58a6ff !important;
		}
	}

	.item-reactions {
		margin: 8px 0 0 40px;
	}

	.settings-link {
		display: flex;
		align-items: center;
		.icon {
			margin-right: 4px;
		}
	}

	.widget-error {
		margin-bottom: 8px;
	}

	.spacer {
		flex-grow: 1;
	}
}
</style>
