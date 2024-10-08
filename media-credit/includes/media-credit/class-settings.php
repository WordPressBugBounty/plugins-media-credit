<?php
/**
 * This file is part of Media Credit.
 *
 * Copyright 2019-2023 Peter Putzer.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 *  ***
 *
 * @package mundschenk-at/media-credit
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Media_Credit;

use Media_Credit\Data_Storage\Options;

use Media_Credit\Vendor\Mundschenk\UI\Controls;

/**
 * Plugin settings for Media Credit.
 *
 * @internal
 *
 * @since 4.0.0
 *
 * @author Peter Putzer <github@mundschenk.at>
 *
 * @phpstan-type SettingsFieldMeta array{ ui: class-string<\Media_Credit\Vendor\Mundschenk\UI\Control>, tab_id: string,
 *     section: string, elements?: mixed[], short?: string, label?: string, help_text: string,
 *     grouped_with?: string, attributes?: mixed[], default?: mixed }
 * @phpstan-type SettingsFieldDefinitions array{ media-credit-preview: SettingsFieldMeta, separator: SettingsFieldMeta,
 *     organization: SettingsFieldMeta, credit_at_end: SettingsFieldMeta, no_default_credit: SettingsFieldMeta,
 *     custom_default_credit: SettingsFieldMeta, post_thumbnail_credit: SettingsFieldMeta, schema_org_markup: SettingsFieldMeta }
 * @phpstan-type SettingsFields array{ separator: string,
 *     organization: string, credit_at_end: bool, no_default_credit: bool,
 *     custom_default_credit: string, featured_image_credit: bool, schema_org_markup: bool,
 *     version: string, install_date: string }
 */
class Settings {
	// Individual settings.
	const INSTALLED_VERSION     = 'version';
	const INSTALL_DATE          = 'install_date';
	const SEPARATOR             = 'separator';
	const ORGANIZATION          = 'organization';
	const CREDIT_AT_END         = 'credit_at_end';
	const NO_DEFAULT_CREDIT     = 'no_default_credit';
	const CUSTOM_DEFAULT_CREDIT = 'custom_default_credit';
	const FEATURED_IMAGE_CREDIT = 'post_thumbnail_credit';
	const SCHEMA_ORG_MARKUP     = 'schema_org_markup';

	// The "Preview" pseudo-setting.
	const MEDIA_CREDIT_PREVIEW = 'media-credit-preview';

	// The section key.
	const SETTINGS_SECTION = 'media-credit';

	/**
	 * The string used to separate the username and the organization
	 * for crediting local WordPress users.
	 *
	 * @var string
	 */
	const DEFAULT_SEPARATOR = ' | ';

	/**
	 * The fields definition array.
	 *
	 * @var array<string, array>
	 *
	 * @phpstan-var SettingsFieldDefinitions
	 */
	private array $fields;

	/**
	 * The defaults array.
	 *
	 * @since 4.2.0
	 *
	 * @var array
	 *
	 * @phpstan-var SettingsFields
	 */
	private array $defaults;

	/**
	 * The user's settings (indexed by site ID to be multisite-safe).
	 *
	 * @since 4.2.0
	 *
	 * @var array
	 *
	 * @phpstan-var SettingsFields
	 */
	private array $settings;

	/**
	 * The plugin version.
	 *
	 * @since 4.2.0
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * The options handler.
	 *
	 * @since 4.2.0
	 *
	 * @var Options
	 */
	private Options $options;

	/**
	 * Creates a new instance.
	 *
	 * @since 4.2.0
	 *
	 * @param string  $version The plugin version string (e.g. "3.0.0-beta.2").
	 * @param Options $options The options handler.
	 */
	public function __construct( $version, Options $options ) {
		$this->version = $version;
		$this->options = $options;
	}

	/**
	 * Retrieves the plugin version.
	 *
	 * @since 4.2.0
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Retrieves the complete plugin settings array.
	 *
	 * @since  4.2.0
	 *
	 * @param  bool $force Optional. Forces retrieval of settings from database.
	 *                     Default false.
	 *
	 * @return array
	 *
	 * @phpstan-return SettingsFields
	 */
	public function get_all_settings( $force = false ) {
		// Force a re-read if the cached settings do not appear to be from the current version.
		if (
			empty( $this->settings ) ||
			empty( $this->settings[ self::INSTALLED_VERSION ] ) ||
			$this->version !== $this->settings[ self::INSTALLED_VERSION ] ||
			$force
		) {
			$this->settings = $this->load_settings();
		}

		return $this->settings;
	}

	/**
	 * Load settings from the database and set defaults if necessary.
	 *
	 * @since  4.2.0
	 *
	 * @return array
	 *
	 * @phpstan-return SettingsFields
	 */
	protected function load_settings() {
		/**
		 * Retrieve options using new name.
		 *
		 * @phpstan-var SettingsFields|false
		 */
		$_settings = $this->options->get( Options::OPTION );
		$_defaults = $this->get_defaults();
		$modified  = false;

		// Maybe we need to use the old option name.
		if ( false === $_settings ) {
			/**
			 * Retrieve options using old name.
			 *
			 * @phpstan-var SettingsFields|false
			 */
			$_settings = $this->options->get( 'media-credit', false, true );

			if ( \is_array( $_settings ) ) {
				// Let's move things to the new name.
				$this->options->delete( 'media-credit', true );
				$modified = true;
			}
		}

		if ( \is_array( $_settings ) ) {
			// Set correct installed version for really old installs (pre-1.0).
			if ( ! isset( $_settings[ self::INSTALLED_VERSION ] ) ) {
				$_settings[ self::INSTALLED_VERSION ] = '0.5.5-or-earlier';
			}

			// Ensure default values are set for all new settings.
			foreach ( $_defaults as $name => $default_value ) {
				if ( ! isset( $_settings[ $name ] ) ) {
					$_settings[ $name ] = $default_value;
					$modified           = true;
				}
			}

			/**
			 * PHPStan type.
			 *
			 * @phpstan-var SettingsFields $_settings
			 */
		} else {
			$_settings = $_defaults;
			$modified  = true;
		}

		if ( $modified ) {
			$this->options->set( Options::OPTION, $_settings );
		}

		return $_settings;
	}

	/**
	 * Retrieves a single setting.
	 *
	 * @since  4.2.0
	 *
	 * @param  string $setting The setting name (index).
	 * @param  bool   $force   Optional. Forces retrieval of settings from database. Default false.
	 *
	 * @return string|int|bool The requested setting value.
	 *
	 * @throws \UnexpectedValueException Thrown when the setting name is invalid.
	 */
	public function get( $setting, $force = false ) {
		$all_settings = $this->get_all_settings( $force );

		if ( ! isset( $all_settings[ $setting ] ) ) {
			throw new \UnexpectedValueException( "Invalid setting name '{$setting}'." );
		}

		return $all_settings[ $setting ];
	}

	/**
	 * Sets a single setting.
	 *
	 * @since  4.2.0
	 *
	 * @internal
	 *
	 * @param  string          $setting The setting name (index).
	 * @param  string|int|bool $value   The setting value.
	 *
	 * @return bool
	 *
	 * @throws \UnexpectedValueException Thrown when the setting name is invalid.
	 *
	 * @phpstan-param key-of<SettingsFields> $setting
	 * @phpstan-param value-of<SettingsFields> $value
	 */
	public function set( $setting, $value ) {
		$all_settings = $this->get_all_settings();

		if ( ! isset( $all_settings[ $setting ] ) ) {
			throw new \UnexpectedValueException( "Invalid setting name '{$setting}'." );
		}

		// Update DB.
		$all_settings[ $setting ] = $value;
		$result                   = $this->options->set( Options::OPTION, $all_settings );

		// Update cached settings only if DB the DB write was successful.
		if ( $result ) {
			/**
			 * PHPStan type.
			 *
			 * @phpstan-var SettingsFields $all_settings
			 */
			$this->settings = $all_settings;
		}

		return $result;
	}

	/**
	 * Retrieves the settings field definitions.
	 *
	 * @return array
	 *
	 * @phpstan-return SettingsFieldDefinitions
	 */
	public function get_fields() {
		if ( empty( $this->fields ) ) {
			$this->fields = [ // @codeCoverageIgnoreStart
				self::MEDIA_CREDIT_PREVIEW      => [
					'ui'             => Controls\Display_Text::class,
					'tab_id'         => '', // Will be added to the 'discussions' page.
					'section'        => self::SETTINGS_SECTION,
					'elements'       => [], // Will be added later.
					'short'          => \__( 'Preview', 'media-credit' ),
					'help_text'      => \__( 'This is what media credits will look like with your current settings.', 'media-credit' ),
				],
				self::SEPARATOR                 => [
					'ui'             => Controls\Text_Input::class,
					'tab_id'         => '', // Will be added to the 'media' page.
					'section'        => self::SETTINGS_SECTION,
					'short'          => \__( 'Separator', 'media-credit' ),
					'help_text'      => \__( 'Text used to separate author names from organization when crediting media to users of this blog.', 'media-credit' ),
					'attributes'     => [ 'class' => 'small-text' ],
					'default'        => self::DEFAULT_SEPARATOR,
				],
				self::ORGANIZATION              => [
					'ui'             => Controls\Text_Input::class,
					'tab_id'         => '', // Will be added to the 'media' page.
					'section'        => self::SETTINGS_SECTION,
					'short'          => \__( 'Organization', 'media-credit' ),
					'help_text'      => \__( 'Organization used when crediting media to users of this blog.', 'media-credit' ),
					'attributes'     => [ 'class' => 'regular-text' ],
					'default'        => \get_bloginfo( 'name' ),
				],
				self::CREDIT_AT_END             => [
					'ui'               => Controls\Checkbox_Input::class,
					'tab_id'           => '',
					'section'          => self::SETTINGS_SECTION,
					'short'            => \__( 'Credit position', 'media-credit' ),
					/* translators: 1: checkbox HTML */
					'label'            => \__( '%1$s Display credit after posts.', 'media-credit' ),
					'help_text'        => \__(
						'Display media credit for all the images attached to a post after the post content. Style with CSS class <code>media-credit-end</code>.',
						'media-credit'
					) . ' <br><strong>' . \__( 'Warning', 'media-credit' ) . '</strong>: ' . \__(
						'This will cause credit for all images in all posts to display at the bottom of every post on this blog.',
						'media-credit'
					),
					'default'          => 0,
				],
				self::FEATURED_IMAGE_CREDIT     => [
					'ui'               => Controls\Checkbox_Input::class,
					'tab_id'           => '',
					'section'          => self::SETTINGS_SECTION,
					/* translators: 1: checkbox HTML */
					'label'            => \__( '%1$s Display credit for featured images.', 'media-credit' ),
					'help_text'        => \__( 'Try to add media credit to featured images (depends on theme support).', 'media-credit' ),
					'grouped_with'     => self::CREDIT_AT_END,
					'default'          => 0,
				],
				self::NO_DEFAULT_CREDIT         => [
					'ui'               => Controls\Checkbox_Input::class,
					'tab_id'           => '',
					'section'          => self::SETTINGS_SECTION,
					'short'            => \__( 'Default credit', 'media-credit' ),
					/* translators: 1: checkbox HTML */
					'label'            => \__( '%1$s Do not credit images to WordPress users.', 'media-credit' ),
					'help_text'        => \__( 'Do not use the attachment author as the default credit.', 'media-credit' ),
					'default'          => 0,
				],
				self::CUSTOM_DEFAULT_CREDIT     => [
					'ui'               => Controls\Text_Input::class,
					'tab_id'           => '',
					'section'          => self::SETTINGS_SECTION,
					'help_text'        => \__( 'Use this custom default credit for new images.', 'media-credit' ),
					'grouped_with'     => self::NO_DEFAULT_CREDIT,
					'attributes'       => [ 'class' => 'regular-text' ],
					'default'          => '',
				],
				self::SCHEMA_ORG_MARKUP         => [
					'ui'               => Controls\Checkbox_Input::class,
					'tab_id'           => '',
					'section'          => self::SETTINGS_SECTION,
					'short'            => \__( 'Structured data', 'media-credit' ),
					/* translators: 1: checkbox HTML */
					'label'            => \__( '%1$s Include schema.org structured data in HTML5 microdata markup.', 'media-credit' ),
					'help_text'        => \__( 'Microdata is added to the credit itself and the surrounding <code>figure</code> and <code>img</code> (if they don\'t already have other microdata set). The setting has no effect if credits are displayed after posts.', 'media-credit' ),
					'default'          => 0,
				],
			]; // @codeCoverageIgnoreEnd
		}

		return $this->fields;
	}

	/**
	 * Retrieves the default settings.
	 *
	 * @since 4.2.0
	 *
	 * @return array
	 *
	 * @phpstan-return SettingsFields
	 */
	public function get_defaults() {
		if ( empty( $this->defaults ) ) {
			$_defaults = [];
			foreach ( $this->get_fields() as $index => $field ) {
				if ( isset( $field['default'] ) ) {
					$_defaults[ $index ] = $field['default'];
				}
			}

			// Allow detection of new installations.
			$_defaults[ self::INSTALLED_VERSION ] = '';
			$_defaults[ self::INSTALL_DATE ]      = \gmdate( 'Y-m-d' );

			/**
			 * PHPStan type.
			 *
			 * @phpstan-var SettingsFields $_defaults
			 */
			$this->defaults = $_defaults;
		}

		return $this->defaults;
	}
}
