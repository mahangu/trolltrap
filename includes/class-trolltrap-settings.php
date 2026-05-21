<?php

if ( ! defined( 'ABSPATH' ) ) exit;

// Settings->Discussion and Comments panel setup class.

class mahangu_Troll_Trap_settings extends mahangu_Troll_Trap {



	public function __construct (  ) {


		// Register and render settings on Settings > Discussion page.
		add_action( 'admin_init', array ($this, 'settings_register' ) );

		// Setup custom 'Troll Trap Filter' column on the Comments panel.
		add_action('manage_comments_custom_column', array ($this, 'admin_column_output'), 10, 2);
		add_filter('manage_edit-comments_columns', array ($this, 'admin_column_setup') );

        add_filter( 'bulk_actions-edit-comments', array ($this, 'register_bulk_actions') );
        add_filter( 'handle_bulk_actions-edit-comments', array ($this, 'handle_bulk_actions'), 1, 3);

        add_action( 'admin_notices', array ($this, 'handle_bulk_actions_notice') );

        // POST endpoint for per-comment filter changes (replaces in-place GET handling).
        add_action( 'admin_post_trolltrap_set_filter', array( $this, 'handle_set_filter' ) );

    }



	function settings_register () {

		add_settings_section(
			'trolltrap',
			'Troll Trap',
			array ($this, 'settings_description'),
			'discussion'
		);

		register_setting(
			'discussion',
			'trolltrap_words',
			array( 'sanitize_callback' => 'sanitize_textarea_field' )
		);

		add_settings_field(
			'trolltrap_words',
			'Comment Graylist',
			array ($this, 'settings_form_words'),
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
			'Default Filter',
			array ($this, 'settings_form_default_filter'),
			'discussion',
			'trolltrap'
		);

	}


	function settings_description() {

		?><p class="description">Options for the Troll Trap plugin.</p><?php

	}


	public function sanitize_default_filter( $value ) {

		$allowed = wp_list_pluck( $this->filters, 'slug' );
		$allowed = array_diff( $allowed, array( 'none' ) ); // 'none' is not a valid default.

		return in_array( $value, $allowed, true ) ? $value : 'piglatin';

	}


	public function settings_form_words() {

		$data = esc_textarea( get_option( 'trolltrap_words', '' ) );

		print ('<p><label for="trolltrap_words">When a comment contains any of these words in its content, name, URL, email, or IP, the default Troll Trap filter will be applied to it. One word or IP per line. It will match inside words, so “press” will match “WordPress”.</label></p>');

		printf(
			'<textarea name="trolltrap_words" rows="10" cols="50" class="large-text code">%1$s</textarea>',
			$data
		);

	}

	public function settings_form_default_filter() {

		$stored_filter = esc_attr( get_option( 'trolltrap_default_filter' ) ) ;

		printf (
			'<p><label for="trolltrap_default_filter">Choose which filter will be applied to comments by default. The filter for individual comments can be edited via the <a href="%1$s">comment moderation interface</a>.</label></p>',
			admin_url( 'edit-comments.php' )

		);


		print '<select name="trolltrap_default_filter" id="trolltrap_default_filter" style="display: block;">';

		foreach ($this->filters as $filter) {

			if ($stored_filter == $filter["slug"] ) {

				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					$filter["slug"], $filter["name"]
				);

			} elseif ( $filter["slug"] != "none" ) { //'None' cannot be a default filter, becuase it does nothing.

				printf(
					'<option value="%1$s">%2$s</option>',
					$filter["slug"], $filter["name"]

				);

			}

		}

		print ("</select>");



	}


     public function register_bulk_actions($bulk_actions) {
        $bulk_actions['mark_as_troll'] = __( 'Mark as Troll', 'mark_as_troll');
        return $bulk_actions;
    }


    public function handle_bulk_actions( $redirect_to, $doaction, $comment_ids ) {

        if ( $doaction !== 'mark_as_troll' ) {
            return $redirect_to;
        }

        if ( ! current_user_can( 'moderate_comments' ) ) {
            return $redirect_to;
        }

        // Core's edit-comments.php already verifies the 'bulk-comments' nonce
        // before this filter fires; re-checking signals intent and protects
        // any caller that invokes the filter directly.
        check_admin_referer( 'bulk-comments' );

        $default_filter = esc_attr(get_option('trolltrap_default_filter', 'piglatin'));


        foreach ( $comment_ids as $comment_id ) {

            update_comment_meta($comment_id, '_trolltrap_filter', $default_filter, '');

        }

        $redirect_to = add_query_arg( 'bulk_troll_comments', count( $comment_ids ), $redirect_to );
        return $redirect_to;
    }


    public function handle_bulk_actions_notice() {
        if ( ! empty( $_REQUEST['bulk_troll_comments'] ) ) {
            $marked_comments = intval( $_REQUEST['bulk_troll_comments'] );
            printf( '<div id="message" class="updated fade">' .
                _n( 'Marked %s comment as Troll.',
                    'Marked %s comments as Troll.',
                    $marked_comments,
                    'mark-as_troll'
                ) . '</div>', $marked_comments );
        }
    }



    public function admin_column_setup($columns) {

		$columns["trolltrap"] = "Troll Trap Filter";
		return $columns;

	}

	public function admin_column_output($colname, $comment_id) {

		$comment_meta = get_comment_meta($comment_id, '_trolltrap_filter', true);

		$paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 0;
		$p     = isset( $_GET['p'] )     ? absint( $_GET['p'] )     : 0;

		printf(
			'<form name="trolltrap" method="POST" action="%1$s">',
			esc_url( admin_url( 'admin-post.php' ) )
		);

		print '<input type="hidden" name="action" value="trolltrap_set_filter">';

		wp_nonce_field( 'trolltrap_set_filter_' . $comment_id );

		if ($comment_meta != "none") {

			print '<select onchange="this.form.submit()" name="trolltrap" id="trolltrap" style="display: block; border: 1px solid red;">';

		} else {

			print '<select onchange="this.form.submit()" name="trolltrap" id="trolltrap" style="display: block;">';

		}

		foreach ($this->filters as $filter) {

			if ($comment_meta == $filter["slug"]) {

				printf(
					'<option value="%1$s" selected="selected">%2$s</option>',
					$filter["slug"], $filter["name"]

				);

			} else {

				printf(
					'<option value="%1$s">%2$s</option>',
					$filter["slug"], $filter["name"]

				);

			}

		}

		print ("</select>");

		printf(
			'<input type="hidden" name="comment_id" value="%d">',
			(int) $comment_id
		);

		printf(
			'<input type="hidden" name="paged" value="%d">',
			$paged
		);

		printf(
			'<input type="hidden" name="p" value="%d">',
			$p
		);


		print ("</form>");

	}


	/**
	 * Handler for the admin-post.php endpoint that updates the per-comment
	 * filter. Replaces the previous GET-form handling inside
	 * admin_column_output() — GET leaked the nonce into URL bar, history,
	 * and the Referer header, and the action verb '_ttnonce' was not
	 * bound to a specific comment.
	 */
	public function handle_set_filter() {

		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'troll-trap' ), 403 );
		}

		$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		if ( ! $comment_id || ! get_comment( $comment_id ) ) {
			wp_die( esc_html__( 'Invalid comment.', 'troll-trap' ), 400 );
		}

		check_admin_referer( 'trolltrap_set_filter_' . $comment_id );

		$allowed = wp_list_pluck( $this->filters, 'slug' );
		$raw     = isset( $_POST['trolltrap'] ) ? sanitize_key( wp_unslash( $_POST['trolltrap'] ) ) : '';
		$filter  = in_array( $raw, $allowed, true ) ? $raw : 'none';

		update_comment_meta( $comment_id, '_trolltrap_filter', $filter );

		$redirect = admin_url( 'edit-comments.php' );
		$paged    = isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 0;
		$p        = isset( $_POST['p'] )     ? absint( $_POST['p'] )     : 0;
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