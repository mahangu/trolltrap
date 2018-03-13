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
			'trolltrap_words'
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
			'trolltrap_default_filter'
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


	public function settings_form_words() {

		$data = esc_attr( get_option( 'trolltrap_words' ) ) ;

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

	    // Need to add a nonce here.

        if ( $doaction !== 'mark_as_troll' ) {
            return $redirect_to;
        }

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

		$paged = $_GET["paged"]; // Need to use get_query_vars() instead

		$p = $_GET["p"]; // Need to use get_query_vars() instead

		$retrieved_nonce = $_GET['_ttnonce'];

		if (isset($_GET["trolltrap"]) && isset($_GET["comment_id"])  && wp_verify_nonce($retrieved_nonce, '_ttnonce' )) {

			$update_id = $_GET["comment_id"];

			$comment_filter = $_GET["trolltrap"];

			if (isset($comment_filter)) {

				delete_comment_meta($update_id, '_trolltrap_filter');
			}

			switch ($comment_filter) {

				case "piglatin":
					update_comment_meta($update_id, '_trolltrap_filter', 'piglatin', true);
					break;

				case "reverse":
					update_comment_meta($update_id, '_trolltrap_filter', 'reverse', true);
					break;

				case "disemvowel":
					update_comment_meta($update_id, '_trolltrap_filter', 'disemvowel', true);
					break;

				case "none":
					update_comment_meta($update_id, '_trolltrap_filter', 'none', true);
					break;
			}
		}

		$comment_meta = get_comment_meta($comment_id, '_trolltrap_filter', true);


		printf(
			'<form name="trolltrap" method="GET" action="%1$s">',
			admin_url('edit-comments.php')

		);

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
					'<option value="%1$s"">%2$s</option>',
					$filter["slug"], $filter["name"]

				);

			}

		}

		print ("</select>");

		$set_nonce = wp_create_nonce('_ttnonce');

		printf(

			'<input type="hidden" name="_ttnonce" value="%1$s">',
			$set_nonce
		);


		printf(
			'<input type="hidden" name="comment_id" value="%1$s">',
			$comment_id
		);


		printf(
			'<input type="hidden" name="paged" value="%1$s">',
			$paged
		);

		printf(
			'<input type="hidden" name="p" value="%1$s">',
			$p
		);


		print ("</form>");

	}



}