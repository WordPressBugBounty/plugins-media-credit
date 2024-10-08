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

use Media_Credit\Vendor\Dice\Dice;

use Media_Credit\Core;
use Media_Credit\Controller;
use Media_Credit\Component;
use Media_Credit\Components;
use Media_Credit\Tools;
use Media_Credit\Settings;
use Media_Credit\Exceptions\Factory_Exception;

use Media_Credit\Vendor\Mundschenk\Data_Storage;

/**
 * A factory for creating Media_Credit instances via dependency injection.
 *
 * @since 4.0.0
 * @since 4.1.0 Class made concrete.
 * @since 4.2.0 Renamed to `Media_Credit\Factory`.
 *
 * @author Peter Putzer <github@mundschenk.at>
 */
class Factory extends Dice {
	const SHARED = [ 'shared' => true ];

	/**
	 * The factory instance.
	 *
	 * @var Factory|null
	 */
	private static $factory;

	/**
	 * Creates a new instance.
	 *
	 * @since 4.1.0
	 */
	final protected function __construct() {}

	/**
	 * Retrieves a factory set up for creating Media_Credit instances.
	 *
	 * @since 4.1.0 Parameter $full_plugin_path replaced with MEDIA_CREDIT_PLUGIN_PATH constant.
	 * @since 4.2.0 Now throws a Factory_Exception in case of error.
	 *
	 * @return Factory
	 *
	 * @throws Factory_Exception An exception is thrown if the factory cannot be created.
	 */
	public static function get() {
		if ( ! isset( self::$factory ) ) {

			// Create factory.
			$factory = new static();
			$factory = $factory->addRules( $factory->get_rules() );

			if ( $factory instanceof Factory ) {
				self::$factory = $factory;
			} else {
				throw new Factory_Exception( 'Could not create object factory.' ); // @codeCoverageIgnore
			}
		}

		return self::$factory;
	}

	/**
	 * Retrieves the rules for setting up the plugin.
	 *
	 * @since 2.1.0
	 *
	 * @return array
	 *
	 * @phpstan-return array<class-string,mixed[]>
	 */
	protected function get_rules() {
		// The plugin version.
		$version = $this->get_plugin_version();

		// Rule helper.
		$version_rule = [
			'constructParams' => [ $version ],
		];

		// Define rules.
		return [
			// Core API.
			Core::class                         => self::SHARED,
			Settings::class                     => [
				'shared'          => true,
				'constructParams' => [ $version ],
			],

			// The plugin controller.
			Controller::class                   => [
				'constructParams' => [ $this->get_components() ],
			],

			// Shared helpers.
			Data_Storage\Cache::class           => self::SHARED,
			Data_Storage\Transients::class      => self::SHARED,
			Data_Storage\Site_Transients::class => self::SHARED,
			Data_Storage\Options::class         => self::SHARED,
			Data_Storage\Network_Options::class => self::SHARED,
			Tools\Author_Query::class           => self::SHARED,
			Tools\Media_Query::class            => self::SHARED,
			Tools\Shortcodes_Filter::class      => self::SHARED,
			Tools\Template::class               => [
				'shared'          => true,
				'constructParams' => [ \MEDIA_CREDIT_PLUGIN_PATH ],
			],

			// Components.
			Component::class                    => self::SHARED,
			Components\Classic_Editor::class    => $version_rule,
		];
	}

	/**
	 * Retrieves the plugin version.
	 *
	 * @since 4.1.0
	 *
	 * @return string
	 */
	protected function get_plugin_version() {
		// Load version from plugin data.
		if ( ! \function_exists( 'get_plugin_data' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return \get_plugin_data( \MEDIA_CREDIT_PLUGIN_FILE, false, false )['Version'];
	}

	/**
	 * Retrieves the list of plugin components run during normal operations
	 * (i.e. not including the Uninstallation component).
	 *
	 * @return array {
	 *     An array of `Component` instances in `Dice` syntax.
	 *
	 *     @type array {
	 *         @type string $instance The classname.
	 *     }
	 * }
	 *
	 * @phpstan-return array<int, array<string, class-string>>
	 */
	protected function get_components() {
		return [
			[ Dice::INSTANCE => Components\Setup::class ],
			[ Dice::INSTANCE => Components\Frontend::class ],
			[ Dice::INSTANCE => Components\Shortcodes::class ],
			[ Dice::INSTANCE => Components\Block_Editor::class ],
			[ Dice::INSTANCE => Components\Classic_Editor::class ],
			[ Dice::INSTANCE => Components\Media_Library::class ],
			[ Dice::INSTANCE => Components\Settings_Page::class ],
			[ Dice::INSTANCE => Components\REST_API::class ],
		];
	}
}
