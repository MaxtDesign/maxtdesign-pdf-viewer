<?php
/**
 * Settings Manager Class
 *
 * Manages all plugin options with proper sanitization, defaults,
 * and WordPress Settings API integration.
 *
 * @package     MaxtDesign\PDFViewer
 * @since       1.0.0
 * @author      MaxtDesign
 * @copyright   2025 MaxtDesign
 * @license     GPL-2.0-or-later
 */

declare(strict_types=1);

namespace MaxtDesign\PDFViewer;

// Security check - exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Settings Manager Class
 *
 * Handles retrieval, storage, sanitization, and defaults for all plugin configuration.
 */
class Settings {

	/**
	 * Option name in WordPress database
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'mdpv_settings';

	/**
	 * Default settings values
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = [
		'version'               => '',
		'db_version'            => 1,
		'generate_on_upload'    => true,
		'preview_quality'       => 'medium',  // low, medium, high
		'default_load_behavior' => 'click',   // click, visible, immediate
		'default_width'         => '100%',
		'toolbar_download'      => true,
		'toolbar_print'         => true,
		'toolbar_fullscreen'    => true,
		'preload_viewer'        => false,
		'cache_duration'        => 30,        // days
	];

	/**
	 * Cached settings array
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $settings = null;

	/**
	 * Get a single setting by key
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Optional default value if key not found.
	 * @return mixed Setting value or default.
	 */
	public function get( string $key, mixed $default = null ): mixed {
		$settings = $this->get_all();

		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}

		if ( null !== $default ) {
			return $default;
		}

		return self::DEFAULTS[ $key ] ?? null;
	}

	/**
	 * Get all settings merged with defaults
	 *
	 * @return array<string, mixed> All settings.
	 */
	public function get_all(): array {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$stored = get_option( self::OPTION_NAME, [] );
		$this->settings = wp_parse_args( $stored, self::DEFAULTS );

		return $this->settings;
	}

	/**
	 * Update a single setting
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, mixed $value ): bool {
		$settings = $this->get_all();
		$settings[ $key ] = $this->sanitize_setting( $key, $value );

		$result = update_option( self::OPTION_NAME, $settings );
		$this->settings = null; // Clear cache.

		return $result;
	}

	/**
	 * Update multiple settings at once
	 *
	 * @param array<string, mixed> $values Array of key-value pairs.
	 * @return bool True on success, false on failure.
	 */
	public function set_many( array $values ): bool {
		$settings = $this->get_all();

		// Only accept keys that exist in DEFAULTS.
		foreach ( $values as $key => $value ) {
			if ( array_key_exists( $key, self::DEFAULTS ) ) {
				$settings[ $key ] = $this->sanitize_setting( $key, $value );
			}
		}

		$result = update_option( self::OPTION_NAME, $settings );
		$this->settings = null; // Clear cache.

		return $result;
	}

	/**
	 * Set default settings on plugin activation
	 *
	 * Uses add_option() to avoid overwriting existing settings.
	 *
	 * @return void
	 */
	public function set_defaults(): void {
		$defaults = self::DEFAULTS;
		$defaults['version'] = Plugin::VERSION;

		add_option( self::OPTION_NAME, $defaults );
		$this->settings = null; // Clear cache.
	}

	/**
	 * Sanitize a single setting value
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value to sanitize.
	 * @return mixed Sanitized value.
	 */
	private function sanitize_setting( string $key, mixed $value ): mixed {
		switch ( $key ) {
			case 'generate_on_upload':
			case 'toolbar_download':
			case 'toolbar_print':
			case 'toolbar_fullscreen':
			case 'preload_viewer':
				return (bool) $value;

			case 'preview_quality':
				return in_array( $value, [ 'low', 'medium', 'high' ], true )
					? $value
					: 'medium';

			case 'default_load_behavior':
				return in_array( $value, [ 'click', 'visible', 'immediate' ], true )
					? $value
					: 'click';

			case 'default_width':
			case 'version':
				return sanitize_text_field( (string) $value );

			case 'cache_duration':
			case 'db_version':
				return absint( $value );

			default:
				return $value;
		}
	}

	/**
	 * Sanitize all settings for Settings API callback
	 *
	 * @param array<string, mixed> $input Raw input from Settings API.
	 * @return array<string, mixed> Sanitized settings.
	 */
	public function sanitize_all( array $input ): array {
		// Get existing settings to preserve values not in form
		$existing = $this->get_all();
		$sanitized = [];

		// Boolean/checkbox fields that should be false when not in input
		$boolean_fields = [
			'generate_on_upload',
			'toolbar_download',
			'toolbar_print',
			'toolbar_fullscreen',
			'preload_viewer',
		];

		// Loop through DEFAULTS keys and sanitize each provided value.
		foreach ( self::DEFAULTS as $key => $default_value ) {
			if ( isset( $input[ $key ] ) ) {
				// Field was submitted, sanitize it
				$sanitized[ $key ] = $this->sanitize_setting( $key, $input[ $key ] );
			} elseif ( in_array( $key, $boolean_fields, true ) ) {
				// For checkboxes, if not in input, set to false (unchecked)
				$sanitized[ $key ] = false;
			} else {
				// For other fields, preserve existing value or use default
				$sanitized[ $key ] = $existing[ $key ] ?? $default_value;
			}
		}

		return $sanitized;
	}

	/**
	 * Register settings with WordPress Settings API
	 *
	 * @return void
	 */
	public function register(): void {
		register_setting(
			'mdpv_settings_group',
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_all' ],
				'default'           => self::DEFAULTS,
			]
		);
	}

	/**
	 * Delete all settings from database
	 *
	 * Used during plugin uninstall.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function delete_all(): bool {
		$this->settings = null; // Clear cache.
		return delete_option( self::OPTION_NAME );
	}
}


