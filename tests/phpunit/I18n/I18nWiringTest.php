<?php
/**
 * GH-88: the plugin must actually be translatable.
 *
 * There is no load_plugin_textdomain(), no wp_set_script_translations(), no
 * languages/ directory, and no block.json declares a textdomain. Every
 * __() call in PHP and every __() in the editor bundle is therefore
 * untranslatable, however many strings get wrapped.
 *
 * @package Simple_Events
 */
class I18nWiringTest extends WP_UnitTestCase {

	/**
	 * The plugin's own block.json files.
	 *
	 * @return array<string>
	 */
	private function block_json_files() {
		$files = glob( SE_SRC_PATH . '/blocks/*/block.json' );

		$this->assertNotEmpty( $files, 'Expected to find block.json files to check.' );

		return $files;
	}

	/**
	 * Every registered block belonging to this plugin.
	 *
	 * @return array<string, WP_Block_Type>
	 */
	private function plugin_blocks() {
		$blocks = array();

		foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( str_starts_with( $name, 'simple-events/' ) ) {
				$blocks[ $name ] = $block_type;
			}
		}

		$this->assertNotEmpty( $blocks, 'Expected simple-events blocks to be registered.' );

		return $blocks;
	}

	/**
	 * Blocks cannot be translated without declaring their textdomain.
	 *
	 * WordPress only translates a block.json's title, description and keywords
	 * when the file names the domain they belong to.
	 *
	 * @return void
	 */
	public function test_every_block_json_declares_the_text_domain() {
		foreach ( $this->block_json_files() as $file ) {
			$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			$this->assertIsArray( $json, sprintf( '%s should be valid JSON.', basename( dirname( $file ) ) ) );
			$this->assertSame(
				'simple-events',
				$json['textdomain'] ?? null,
				sprintf( '%s/block.json must declare "textdomain": "simple-events".', basename( dirname( $file ) ) )
			);
		}
	}

	/**
	 * Each block's editor script must be wired for translation.
	 *
	 * Without wp_set_script_translations() the editor never requests the
	 * plugin's JSON translation files, so editor strings stay in English
	 * whatever the site language.
	 *
	 * @return void
	 */
	public function test_block_editor_scripts_have_translations_set() {
		$scripts  = wp_scripts();
		$checked  = 0;

		foreach ( $this->plugin_blocks() as $name => $block_type ) {
			foreach ( (array) $block_type->editor_script_handles as $handle ) {
				$this->assertArrayHasKey( $handle, $scripts->registered, sprintf( '%s: script %s should be registered.', $name, $handle ) );
				$this->assertSame(
					'simple-events',
					$scripts->registered[ $handle ]->textdomain ?? null,
					sprintf( '%s: wp_set_script_translations() was never called for %s.', $name, $handle )
				);
				++$checked;
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No editor scripts were found to check.' );
	}

	/**
	 * The translation files have somewhere to live.
	 *
	 * @return void
	 */
	public function test_the_languages_directory_exists() {
		$this->assertDirectoryExists(
			SE_PLUGIN_DIR . '/languages',
			'load_plugin_textdomain() and wp_set_script_translations() both point at languages/.'
		);
	}

	/**
	 * A .pot exists and covers PHP strings.
	 *
	 * @return void
	 */
	public function test_the_pot_file_covers_php_strings() {
		$pot = SE_PLUGIN_DIR . '/languages/simple-events.pot';

		$this->assertFileExists( $pot, 'Run `wp i18n make-pot . languages/simple-events.pot`.' );

		$contents = (string) file_get_contents( $pot ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertStringContainsString(
			'"Project-Id-Version: Simple Events',
			$contents,
			'The .pot should be generated for this plugin.'
		);
		$this->assertStringContainsString(
			'src/classes/class-se-settings.php',
			$contents,
			'Settings strings should have been extracted.'
		);
	}

	/**
	 * The .pot covers the editor, not just PHP.
	 *
	 * The issue calls this out specifically: a string sweep is pointless if the
	 * extraction never reaches the editor sources.
	 *
	 * @return void
	 */
	public function test_the_pot_file_covers_editor_strings() {
		$pot = SE_PLUGIN_DIR . '/languages/simple-events.pot';

		$this->assertFileExists( $pot );

		$contents = (string) file_get_contents( $pot ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$this->assertMatchesRegularExpression(
			'~^#: .*src/blocks/.*\.js:~m',
			$contents,
			'No editor JS references in the .pot — the extraction did not reach src/blocks.'
		);
	}
}
