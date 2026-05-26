<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Settings->Discussion and Comments panel setup class.

class Mahangu_Troll_Trap_Settings {

	/**
	 * The filter registry.
	 *
	 * @var Mahangu_Troll_Trap_Filters
	 */
	private $filters;


	public function __construct( $filters ) {

		$this->filters = $filters;

		// Register and render settings on Settings > Discussion page.
		add_action( 'admin_init', array( $this, 'settings_register' ) );

		// Setup custom 'Troll Trap Filter' column on the Comments panel.
		add_action( 'manage_comments_custom_column', array( $this, 'admin_column_output' ), 10, 2 );
		add_filter( 'manage_edit-comments_columns', array( $this, 'admin_column_setup' ) );

		add_filter( 'bulk_actions-edit-comments', array( $this, 'register_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-comments', array( $this, 'handle_bulk_actions' ), 1, 3 );

		add_action( 'admin_notices', array( $this, 'handle_bulk_actions_notice' ) );

		// POST endpoint for per-comment filter changes (replaces in-place GET handling).
		add_action( 'admin_post_trolltrap_set_filter', array( $this, 'handle_set_filter' ) );

		// POST endpoint for "Regenerate AI rewrite" per-comment button.
		add_action( 'admin_post_trolltrap_regenerate_ai', array( $this, 'handle_regenerate_ai' ) );

		// Troll Trap stats widget on the admin Dashboard.
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
	}


	/**
	 * Register the Troll Trap dashboard widget. Visible to anyone who can
	 * moderate comments, so editors and admins see the same stats.
	 */
	public function register_dashboard_widget() {

		if ( ! current_user_can( 'moderate_comments' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'trolltrap_dashboard',
			__( 'Troll Trap', 'troll-trap' ),
			array( $this, 'render_dashboard_widget' )
		);
	}


	/**
	 * Render the Troll Trap dashboard widget body. Reads commentmeta directly
	 * in one GROUP BY plus one allowlist count, so the widget stays cheap
	 * even on sites with a long comment history.
	 */
	public function render_dashboard_widget() {

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_value AS filter_slug, COUNT(*) AS comment_count FROM {$wpdb->commentmeta} WHERE meta_key = %s AND meta_value != %s GROUP BY meta_value ORDER BY comment_count DESC",
				'_trolltrap_filter',
				'none'
			),
			ARRAY_A
		);

		$allowlisted = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = %s",
				'_trolltrap_allowed'
			)
		);

		$total_trapped = 0;
		foreach ( (array) $rows as $row ) {
			$total_trapped += (int) $row['comment_count'];
		}

		printf(
			'<p style="font-size: 1.1em; margin-top: 0;"><strong>%s</strong></p>',
			esc_html(
				sprintf(
					/* translators: %s: number of trapped comments. */
					_n( '%s comment is currently trapped.', '%s comments are currently trapped.', $total_trapped, 'troll-trap' ),
					number_format_i18n( $total_trapped )
				)
			)
		);

		if ( ! empty( $rows ) ) {
			print '<ul style="margin: 0 0 1em 0;">';
			foreach ( $rows as $row ) {
				$slug   = (string) $row['filter_slug'];
				$count  = (int) $row['comment_count'];
				$filter = $this->filters->get( $slug );
				$name   = ( $filter && isset( $filter['name'] ) ) ? (string) $filter['name'] : $slug;

				printf(
					'<li style="margin: 2px 0;">%1$s &mdash; %2$s</li>',
					esc_html( $name ),
					esc_html( number_format_i18n( $count ) )
				);
			}
			print '</ul>';
		}

		if ( $allowlisted > 0 ) {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of allowlisted comments. */
						_n( '%s comment has been allowlisted.', '%s comments have been allowlisted.', $allowlisted, 'troll-trap' ),
						number_format_i18n( $allowlisted )
					)
				)
			);
		}

		printf(
			'<p><a href="%1$s">%2$s</a> &nbsp;|&nbsp; <a href="%3$s">%4$s</a></p>',
			esc_url( admin_url( 'edit-comments.php' ) ),
			esc_html__( 'Review trapped comments', 'troll-trap' ),
			esc_url( admin_url( 'options-discussion.php#trolltrap' ) ),
			esc_html__( 'Settings', 'troll-trap' )
		);
	}



	public function settings_register() {

		add_settings_section(
			'trolltrap',
			__( 'Troll Trap', 'troll-trap' ),
			array( $this, 'settings_description' ),
			'discussion'
		);

		register_setting(
			'discussion',
			'trolltrap_words',
			array( 'sanitize_callback' => 'sanitize_textarea_field' )
		);

		add_settings_field(
			'trolltrap_words',
			__( 'Comment Graylist', 'troll-trap' ),
			array( $this, 'settings_form_words' ),
			'discussion',
			'trolltrap'
		);

		register_setting(
			'discussion',
			'trolltrap_allowed',
			array( 'sanitize_callback' => 'sanitize_textarea_field' )
		);

		add_settings_field(
			'trolltrap_allowed',
			__( 'Comment Allowlist', 'troll-trap' ),
			array( $this, 'settings_form_allowed' ),
			'discussion',
			'trolltrap'
		);

		register_setting(
			'discussion',
			'trolltrap_default_filter',
			array( 'sanitize_callback' => array( $this, 'sanitize_default_filter' ) )
		);

		add_settings_field(
			'trolltrap_default_filter',
			__( 'Default Filter', 'troll-trap' ),
			array( $this, 'settings_form_default_filter' ),
			'discussion',
			'trolltrap'
		);

		register_setting(
			'discussion',
			'trolltrap_disabled_filters',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_disabled_filters' ),
				'default'           => array(),
			)
		);

		add_settings_field(
			'trolltrap_disabled_filters',
			__( 'Disabled Filters', 'troll-trap' ),
			array( $this, 'settings_form_disabled_filters' ),
			'discussion',
			'trolltrap'
		);

		register_setting(
			'discussion',
			'trolltrap_graduated_enabled',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_graduated_enabled' ),
				'default'           => '0',
			)
		);

		register_setting(
			'discussion',
			'trolltrap_severity_ladder',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_severity_ladder' ),
				'default'           => Mahangu_Troll_Trap::default_severity_ladder(),
			)
		);

		add_settings_field(
			'trolltrap_graduated_severity',
			__( 'Graduated Severity', 'troll-trap' ),
			array( $this, 'settings_form_graduated' ),
			'discussion',
			'trolltrap'
		);
	}


	public function settings_description() {

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Options for the Troll Trap plugin.', 'troll-trap' )
		);
	}


	public function sanitize_default_filter( $value ) {

		$allowed = array_keys( $this->filters->transforming() );

		return in_array( $value, $allowed, true ) ? $value : 'piglatin';
	}


	/**
	 * Sanitize the graduated-severity toggle to '1' or '0'.
	 *
	 * @param mixed $value Raw option value.
	 * @return string
	 */
	/**
	 * Sanitize the disabled-filters list: keep only slugs of currently
	 * registered transforming filters. Drops anything else.
	 *
	 * @param mixed $value Raw option value.
	 * @return string[]
	 */
	public function sanitize_disabled_filters( $value ) {

		if ( ! is_array( $value ) ) {
			return array();
		}

		$known = array_keys( $this->filters->transforming() );
		$out   = array();

		foreach ( $value as $slug ) {
			$slug = sanitize_key( $slug );
			if ( '' !== $slug && in_array( $slug, $known, true ) && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}

		return $out;
	}


	public function sanitize_graduated_enabled( $value ) {

		return ( '1' === (string) $value ) ? '1' : '0';
	}


	/**
	 * Sanitize the graduated-severity ladder: each of the three rungs must map
	 * to a registered transforming filter, falling back to the default ladder.
	 *
	 * @param mixed $value Raw option value.
	 * @return array<int,string>
	 */
	public function sanitize_severity_ladder( $value ) {

		$allowed = array_keys( $this->filters->transforming() );
		$default = Mahangu_Troll_Trap::default_severity_ladder();
		$clean   = array();

		foreach ( array( 1, 2, 3 ) as $rung ) {
			$slug           = ( is_array( $value ) && isset( $value[ $rung ] ) ) ? sanitize_key( $value[ $rung ] ) : '';
			$clean[ $rung ] = in_array( $slug, $allowed, true ) ? $slug : $default[ $rung ];
		}

		return $clean;
	}


	public function settings_form_words() {

		printf(
			'<p><label for="trolltrap_words">%s</label></p>',
			esc_html__( 'When a comment contains any of these words in its content, author name, URL, email, IP address, or user agent, the default Troll Trap filter will be applied to it. One word or IP per line. It matches inside words, so “press” will match “WordPress”.', 'troll-trap' )
		);

		printf(
			'<textarea name="trolltrap_words" rows="10" cols="50" class="large-text code">%s</textarea>',
			esc_textarea( get_option( 'trolltrap_words', '' ) )
		);
	}


	public function settings_form_allowed() {

		printf(
			'<p><label for="trolltrap_allowed">%s</label></p>',
			esc_html__( 'Trusted authors, emails, URLs, IP addresses, or user-agent fragments that should never be trapped. Matched only against the comment author identity, not the comment body, so a troll cannot bypass the graylist by quoting a trusted email. One entry per line.', 'troll-trap' )
		);

		printf(
			'<textarea name="trolltrap_allowed" rows="6" cols="50" class="large-text code">%s</textarea>',
			esc_textarea( get_option( 'trolltrap_allowed', '' ) )
		);
	}


	public function settings_form_disabled_filters() {

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Hide individual filters from the Default Filter, Severity Ladder, AI Fallback, and Mark-as-Troll choices. Previously-assigned filters keep rendering for already-trapped comments, so disabling here just narrows the choices available going forward.', 'troll-trap' )
		);

		$disabled = (array) get_option( 'trolltrap_disabled_filters', array() );

		// Hidden field so an empty submission (all boxes unchecked) still
		// posts a value and clears the option, instead of leaving the
		// previous selection in place.
		print '<input type="hidden" name="trolltrap_disabled_filters[]" value="">';

		foreach ( $this->filters->transforming() as $filter ) {

			$is_disabled = in_array( $filter['slug'], $disabled, true );

			printf(
				'<p style="margin: 4px 0;"><label><input type="checkbox" name="trolltrap_disabled_filters[]" value="%1$s"%2$s> %3$s</label></p>',
				esc_attr( $filter['slug'] ),
				$is_disabled ? ' checked="checked"' : '',
				esc_html( $filter['name'] )
			);
		}
	}


	public function settings_form_default_filter() {

		$stored_filter = esc_attr( get_option( 'trolltrap_default_filter', 'piglatin' ) );

		printf(
			'<p><label for="trolltrap_default_filter">%s</label></p>',
			esc_html__( 'Choose which filter is applied to comments by default. You can change the filter for an individual comment from the Comments screen.', 'troll-trap' )
		);

		print '<select name="trolltrap_default_filter" id="trolltrap_default_filter" style="display: block;">';

		foreach ( $this->filters->enabled() as $filter ) {

			if ( $filter['slug'] === $stored_filter ) {

				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					esc_attr( $filter['slug'] ),
					esc_html( $filter['name'] )
				);

			} else {

				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $filter['slug'] ),
					esc_html( $filter['name'] )
				);

			}
		}

		print '</select>';

		$this->render_filter_preview_table();
	}


	/**
	 * Render a preview table showing each transforming filter applied to a
	 * sample sentence. Helps administrators pick a default filter without
	 * having to trap a comment to see what each one does.
	 *
	 * The 'llm' filter is skipped because it has no inline callback; its
	 * output is generated per comment by the Anthropic API and cannot be
	 * previewed statically.
	 */
	private function render_filter_preview_table() {

		// Reuse the AI feature's test sample as the single source so the
		// filter previews and the AI test rewrite never drift apart.
		$sample = mahangu_troll_trap()->ai->test_sample();

		printf(
			'<p class="description" style="margin-top: 1em;">%1$s <em>%2$s</em></p>',
			esc_html__( 'Preview of each filter, applied to:', 'troll-trap' ),
			esc_html( $sample )
		);

		print '<table class="widefat striped" style="max-width: 720px; margin-top: 0.5em;">';
		printf(
			'<thead><tr><th>%1$s</th><th>%2$s</th></tr></thead><tbody>',
			esc_html__( 'Filter', 'troll-trap' ),
			esc_html__( 'Preview', 'troll-trap' )
		);

		foreach ( $this->filters->enabled() as $filter ) {

			if ( null === $filter['callback'] ) {
				continue;
			}

			printf(
				'<tr><td><strong>%1$s</strong></td><td><code style="word-break: break-word;">%2$s</code></td></tr>',
				esc_html( $filter['name'] ),
				esc_html( $this->filters->apply( $filter['slug'], $sample ) )
			);
		}

		print '</tbody></table>';
	}


	public function settings_form_graduated() {

		$enabled = (string) get_option( 'trolltrap_graduated_enabled', '0' );
		$ladder  = get_option( 'trolltrap_severity_ladder', Mahangu_Troll_Trap::default_severity_ladder() );

		if ( ! is_array( $ladder ) ) {
			$ladder = Mahangu_Troll_Trap::default_severity_ladder();
		}

		// A hidden field guarantees a value posts even when the box is unchecked.
		print '<input type="hidden" name="trolltrap_graduated_enabled" value="0">';

		if ( '1' === $enabled ) {
			print '<p><label><input type="checkbox" name="trolltrap_graduated_enabled" value="1" checked="checked"> ';
		} else {
			print '<p><label><input type="checkbox" name="trolltrap_graduated_enabled" value="1"> ';
		}

		print esc_html__( 'Escalate the filter with the number of graylist keywords a comment matches, instead of always using the Default Filter.', 'troll-trap' );
		print '</label></p>';

		$rungs = array(
			1 => __( '1 matched keyword', 'troll-trap' ),
			2 => __( '2 matched keywords', 'troll-trap' ),
			3 => __( '3 or more matched keywords', 'troll-trap' ),
		);

		foreach ( $rungs as $rung => $label ) {

			$selected_slug = isset( $ladder[ $rung ] ) ? $ladder[ $rung ] : '';

			printf( '<p><label>%s &nbsp;&rarr;&nbsp; ', esc_html( $label ) );
			printf( '<select name="trolltrap_severity_ladder[%d]">', (int) $rung );

			foreach ( $this->filters->enabled() as $filter ) {
				if ( $filter['slug'] === $selected_slug ) {
					printf(
						'<option value="%1$s" selected="selected">%2$s</option>',
						esc_attr( $filter['slug'] ),
						esc_html( $filter['name'] )
					);
				} else {
					printf(
						'<option value="%1$s">%2$s</option>',
						esc_attr( $filter['slug'] ),
						esc_html( $filter['name'] )
					);
				}
			}

			print '</select></label></p>';
		}
	}


	public function register_bulk_actions( $bulk_actions ) {
		$bulk_actions['mark_as_troll'] = __( 'Mark as Troll', 'troll-trap' );
		$bulk_actions['untrap']        = __( 'Untrap (clear filter)', 'troll-trap' );
		$bulk_actions['reevaluate']    = __( 'Re-evaluate against graylist', 'troll-trap' );
		return $bulk_actions;
	}


	public function handle_bulk_actions( $redirect_to, $doaction, $comment_ids ) {

		if ( 'mark_as_troll' !== $doaction && 'untrap' !== $doaction && 'reevaluate' !== $doaction ) {
			return $redirect_to;
		}

		if ( ! current_user_can( 'moderate_comments' ) ) {
			return $redirect_to;
		}

		// Core's edit-comments.php already verifies the 'bulk-comments' nonce
		// before this filter fires; re-checking signals intent and protects
		// any caller that invokes the filter directly.
		check_admin_referer( 'bulk-comments' );

		if ( 'mark_as_troll' === $doaction ) {

			$default_filter = get_option( 'trolltrap_default_filter', 'piglatin' );
			$allowed        = array_keys( $this->filters->transforming() );
			if ( ! in_array( $default_filter, $allowed, true ) ) {
				$default_filter = 'piglatin';
			}

			foreach ( $comment_ids as $comment_id ) {
				update_comment_meta( absint( $comment_id ), '_trolltrap_filter', $default_filter );
			}

			return add_query_arg( 'bulk_troll_comments', count( $comment_ids ), $redirect_to );
		}

		if ( 'reevaluate' === $doaction ) {

			$tt = mahangu_troll_trap();

			foreach ( $comment_ids as $comment_id ) {
				$cid = absint( $comment_id );
				if ( $cid && get_comment( $cid ) ) {
					// Drop any cached AI rewrite so it gets regenerated for
					// the current content if the comment is re-tagged 'llm',
					// and so a stale rewrite cannot survive a move off 'llm'.
					delete_comment_meta( $cid, '_trolltrap_llm_text' );
					$tt->comments_tag( $cid );
				}
			}

			return add_query_arg( 'bulk_reevaluate_comments', count( $comment_ids ), $redirect_to );
		}

		// 'untrap' — clear the filter on selected comments.
		foreach ( $comment_ids as $comment_id ) {
			update_comment_meta( absint( $comment_id ), '_trolltrap_filter', 'none' );
		}

		return add_query_arg( 'bulk_untrap_comments', count( $comment_ids ), $redirect_to );
	}


	public function handle_bulk_actions_notice() {

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only counts for a post-redirect admin notice; the originating bulk action verified the nonce.
		$trolled     = isset( $_REQUEST['bulk_troll_comments'] ) ? intval( $_REQUEST['bulk_troll_comments'] ) : 0;
		$untrapped   = isset( $_REQUEST['bulk_untrap_comments'] ) ? intval( $_REQUEST['bulk_untrap_comments'] ) : 0;
		$reevaluated = isset( $_REQUEST['bulk_reevaluate_comments'] ) ? intval( $_REQUEST['bulk_reevaluate_comments'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $trolled > 0 ) {
			printf(
				'<div id="message" class="updated fade"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of comments. */
						_n(
							'Marked %s comment as Troll.',
							'Marked %s comments as Troll.',
							$trolled,
							'troll-trap'
						),
						number_format_i18n( $trolled )
					)
				)
			);
		}

		if ( $untrapped > 0 ) {
			printf(
				'<div id="message" class="updated fade"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of comments. */
						_n(
							'Untrapped %s comment.',
							'Untrapped %s comments.',
							$untrapped,
							'troll-trap'
						),
						number_format_i18n( $untrapped )
					)
				)
			);
		}

		if ( $reevaluated > 0 ) {
			printf(
				'<div id="message" class="updated fade"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: number of comments. */
						_n(
							'Re-evaluated %s comment against the graylist.',
							'Re-evaluated %s comments against the graylist.',
							$reevaluated,
							'troll-trap'
						),
						number_format_i18n( $reevaluated )
					)
				)
			);
		}
	}


	public function admin_column_setup( $columns ) {

		$columns['trolltrap'] = __( 'Troll Trap Filter', 'troll-trap' );
		return $columns;
	}


	public function admin_column_output( $colname, $comment_id ) {

		$comment_meta = get_comment_meta( $comment_id, '_trolltrap_filter', true );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only pagination values used to rebuild the form's return URL.
		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
		$p     = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		printf(
			'<form name="trolltrap" method="POST" action="%1$s">',
			esc_url( admin_url( 'admin-post.php' ) )
		);

		print '<input type="hidden" name="action" value="trolltrap_set_filter">';

		wp_nonce_field( 'trolltrap_set_filter_' . $comment_id );

		if ( 'none' !== $comment_meta ) {

			print '<select onchange="this.form.submit()" name="trolltrap" style="display: block; border: 1px solid red;">';

		} else {

			print '<select onchange="this.form.submit()" name="trolltrap" style="display: block;">';

		}

		foreach ( $this->filters->all() as $filter ) {

			if ( $filter['slug'] === $comment_meta ) {

				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					esc_attr( $filter['slug'] ),
					esc_html( $filter['name'] )
				);

			} else {

				printf(
					'<option value="%1$s">%2$s</option>',
					esc_attr( $filter['slug'] ),
					esc_html( $filter['name'] )
				);

			}
		}

		print '</select>';

		printf(
			'<input type="hidden" name="comment_id" value="%d">',
			(int) $comment_id
		);

		printf(
			'<input type="hidden" name="paged" value="%d">',
			(int) $paged
		);

		printf(
			'<input type="hidden" name="p" value="%d">',
			(int) $p
		);

		print '</form>';

		$match_count      = (int) get_comment_meta( $comment_id, '_trolltrap_match_count', true );
		$matched_keywords = get_comment_meta( $comment_id, '_trolltrap_matched_keywords', true );
		$allowed_hits     = get_comment_meta( $comment_id, '_trolltrap_allowed', true );

		if ( is_array( $allowed_hits ) && ! empty( $allowed_hits ) ) {
			printf(
				'<p class="description" style="margin: 4px 0 0;">%1$s <code>%2$s</code></p>',
				esc_html__( 'Allowlisted by:', 'troll-trap' ),
				esc_html( implode( ', ', $allowed_hits ) )
			);
		} elseif ( is_array( $matched_keywords ) && ! empty( $matched_keywords ) ) {
			printf(
				'<p class="description" style="margin: 4px 0 0;">%1$s <code>%2$s</code></p>',
				esc_html__( 'Matched graylist keywords:', 'troll-trap' ),
				esc_html( implode( ', ', $matched_keywords ) )
			);
		} elseif ( $match_count > 0 ) {
			printf(
				'<p class="description" style="margin: 4px 0 0;">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of graylist keywords matched. */
						_n(
							'Matched %s graylist keyword.',
							'Matched %s graylist keywords.',
							$match_count,
							'troll-trap'
						),
						number_format_i18n( $match_count )
					)
				)
			);
		}

		if ( 'llm' === $comment_meta ) {

			$tt         = mahangu_troll_trap();
			$has_cache  = ( $tt && $tt->ai && null !== $tt->ai->cached_text( $comment_id ) );
			$has_failed = ( $tt && $tt->ai && $tt->ai->has_failed( $comment_id ) );

			if ( $has_cache ) {
				$status = __( 'AI rewrite ready.', 'troll-trap' );
			} elseif ( $has_failed ) {
				$status = __( 'AI rewrite failed after retries; showing the fallback filter. Use Regenerate to try again.', 'troll-trap' );
			} else {
				$status = __( 'AI rewrite pending; showing the fallback filter.', 'troll-trap' );
			}

			printf(
				'<p class="description" style="margin: 4px 0 0;">%s</p>',
				esc_html( $status )
			);

			if ( $has_cache || $has_failed ) {
				printf(
					'<form method="POST" action="%1$s" style="margin: 4px 0 0;">',
					esc_url( admin_url( 'admin-post.php' ) )
				);
				print '<input type="hidden" name="action" value="trolltrap_regenerate_ai">';
				wp_nonce_field( 'trolltrap_regenerate_ai_' . $comment_id );
				printf( '<input type="hidden" name="comment_id" value="%d">', (int) $comment_id );
				printf( '<input type="hidden" name="paged" value="%d">', (int) $paged );
				printf( '<input type="hidden" name="p" value="%d">', (int) $p );
				printf(
					'<button type="submit" class="button-link">%s</button>',
					esc_html__( 'Regenerate AI rewrite', 'troll-trap' )
				);
				print '</form>';
			}
		}
	}


	/**
	 * Handler for the admin-post.php endpoint that updates the per-comment
	 * filter. Replaces the previous GET-form handling inside
	 * admin_column_output() — GET leaked the nonce into URL bar, history,
	 * and the Referer header, and the action verb '_ttnonce' was not
	 * bound to a specific comment.
	 */
	public function handle_set_filter() {

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'POST is required.', 'troll-trap' ), 405 );
		}

		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'troll-trap' ), 403 );
		}

		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		if ( ! $comment_id || ! get_comment( $comment_id ) ) {
			wp_die( esc_html__( 'Invalid comment.', 'troll-trap' ), 400 );
		}

		check_admin_referer( 'trolltrap_set_filter_' . $comment_id );

		$allowed = $this->filters->slugs();
		$raw     = isset( $_POST['trolltrap'] ) ? sanitize_key( wp_unslash( $_POST['trolltrap'] ) ) : '';
		$filter  = in_array( $raw, $allowed, true ) ? $raw : 'none';

		update_comment_meta( $comment_id, '_trolltrap_filter', $filter );

		$redirect = admin_url( 'edit-comments.php' );
		$paged    = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 0;
		$p        = isset( $_POST['p'] ) ? absint( $_POST['p'] ) : 0;
		if ( $paged ) {
			$redirect = add_query_arg( 'paged', $paged, $redirect );
		}
		if ( $p ) {
			$redirect = add_query_arg( 'p', $p, $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}


	/**
	 * Handler for the per-comment "Regenerate AI rewrite" button. Drops the
	 * cached rewrite for one comment and re-queues the wp-cron job so a fresh
	 * one is requested on the next cron tick.
	 *
	 * Useful when an admin gets a poor or off-style rewrite and wants another
	 * shot without touching the comment or the AI settings.
	 */
	public function handle_regenerate_ai() {

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			wp_die( esc_html__( 'POST is required.', 'troll-trap' ), 405 );
		}

		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'troll-trap' ), 403 );
		}

		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		if ( ! $comment_id || ! get_comment( $comment_id ) ) {
			wp_die( esc_html__( 'Invalid comment.', 'troll-trap' ), 400 );
		}

		check_admin_referer( 'trolltrap_regenerate_ai_' . $comment_id );

		$tt = mahangu_troll_trap();
		if ( $tt && $tt->ai ) {
			$tt->ai->regenerate( $comment_id );
		} else {
			delete_comment_meta( $comment_id, '_trolltrap_llm_text' );
		}

		$redirect = admin_url( 'edit-comments.php' );
		$paged    = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 0;
		$p        = isset( $_POST['p'] ) ? absint( $_POST['p'] ) : 0;
		if ( $paged ) {
			$redirect = add_query_arg( 'paged', $paged, $redirect );
		}
		if ( $p ) {
			$redirect = add_query_arg( 'p', $p, $redirect );
		}

		wp_safe_redirect( $redirect );
		exit;
	}
}
