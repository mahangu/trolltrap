<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of comment-obfuscation filters.
 *
 * Each filter has a slug, a human-readable name, an optional transform callback
 * (receives a string and returns a string; null means "no transformation"),
 * and a severity weight where 0 means the filter does not transform text.
 *
 * Third-party code adds filters on the 'trolltrap_register_filters' action:
 *
 *     add_action( 'trolltrap_register_filters', function ( $filters ) {
 *         $filters->register( 'rot13', 'ROT13', 'str_rot13', 2 );
 *     } );
 */
class Mahangu_Troll_Trap_Filters {

	/**
	 * Registered filters, keyed by slug.
	 *
	 * @var array[]
	 */
	private $filters = array();

	/**
	 * Register a filter.
	 *
	 * @param string        $slug     Unique slug; sanitized to [a-z0-9_-].
	 * @param string        $name     Human-readable, translated display name.
	 * @param callable|null $callback Transform callback, or null for an identity (no-op) filter.
	 * @param int           $severity Escalation weight; 0 means the filter does not transform text.
	 */
	public function register( $slug, $name, $callback = null, $severity = 1 ) {

		$slug = sanitize_key( $slug );

		if ( '' === $slug ) {
			return;
		}

		$this->filters[ $slug ] = array(
			'slug'     => $slug,
			'name'     => (string) $name,
			'callback' => is_callable( $callback ) ? $callback : null,
			'severity' => (int) $severity,
		);
	}

	/**
	 * Whether a filter is registered.
	 *
	 * @param string $slug Filter slug.
	 * @return bool
	 */
	public function has( $slug ) {
		return isset( $this->filters[ $slug ] );
	}

	/**
	 * Get a single registered filter.
	 *
	 * @param string $slug Filter slug.
	 * @return array|null The filter array, or null if not registered.
	 */
	public function get( $slug ) {
		return isset( $this->filters[ $slug ] ) ? $this->filters[ $slug ] : null;
	}

	/**
	 * All registered filters, keyed by slug.
	 *
	 * @return array[]
	 */
	public function all() {
		return $this->filters;
	}

	/**
	 * All registered filter slugs.
	 *
	 * @return string[]
	 */
	public function slugs() {
		return array_keys( $this->filters );
	}

	/**
	 * Filters that actually transform text (severity greater than zero) — i.e.
	 * those eligible to be a default or graylist filter. Excludes 'none'.
	 *
	 * @return array[]
	 */
	public function transforming() {
		return array_filter(
			$this->filters,
			static function ( $filter ) {
				return $filter['severity'] > 0;
			}
		);
	}

	/**
	 * Apply a registered filter to a string.
	 *
	 * Unknown slugs, and filters registered without a callback, return the text
	 * unchanged.
	 *
	 * @param string $slug Filter slug.
	 * @param string $text Text to transform.
	 * @return string
	 */
	public function apply( $slug, $text ) {

		$filter = $this->get( $slug );

		if ( null === $filter || null === $filter['callback'] ) {
			return $text;
		}

		return (string) call_user_func( $filter['callback'], $text );
	}
}
