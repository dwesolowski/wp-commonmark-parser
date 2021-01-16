<?php
/**
 * Plugin Name: WP CommonMark Parser
 * Plugin URI:
 * Description: A simple plugin to add Markdown support to WordPress.
 * Version: 1.0
 * Author: Daren Wesolowski
 * Author URI: https://github.com/dwesolowski/wp-commonmark-parser
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Copyright (C) 2018  Daren Wesolowski
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require __DIR__ . '/vendor/autoload.php';
}

use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use League\CommonMark\Extras\CommonMarkExtrasExtension;
use Webuni\CommonMark\AttributesExtension\AttributesExtension;
use RZ\CommonMark\Ext\Footnote\FootnoteExtension;

$config = [
    'renderer' => [
        'block_separator' => "\n\n",    // String to use for separating renderer block elements
        'inner_separator' => "\n",      // String to use for separating inner block contents
        'soft_break'      => "\n",      // String to use for rendering soft breaks
    ],
        'enable_em' => true,            // Disable <em> parsing by setting to false; enable with true (default: true)
        'enable_strong' => true,        // Disable <strong> parsing by setting to false; enable with true (default: true)
        'use_asterisk' => true,         // Disable parsing of * for emphasis by setting to false; enable with true
        'use_underscore' => false,      // Disable parsing of _ for emphasis by setting to false; enable with true (default: true)
				'unordered_list_markers' => ['-', '*', '+'],   // Array of characters that can be used to indicated a bulleted list (default: ["-", "*", "+"])
        'html_input' => 'allow',        // How to handle HTML input. Set this option to one of the following strings: strip, allow, escape
        'allow_unsafe_links' => false,  // Remove risky link and image URLs by setting this to false (default: true)
        'max_nesting_level' => INF      // The maximum nesting level for blocks (default: infinite 'INF' or use a possitive 'int' value)
];

class CommonMarkParser {

    public function __construct() {

        /* Disable the default visual editor */
        add_filter( 'get_user_option_rich_editing', '__return_false' );
        add_action( 'admin_head', array( $this, 'remove_profile_editor_options' ) );

        add_filter( 'quicktags_settings', array( $this, 'remove_default_quicktags' ) );
        add_action( 'admin_print_footer_scripts', array( $this, 'add_markdown_quicktags' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_commonmark_parser_admin_styles' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_commonmark_parser_styles' ) );
        add_action( 'post_submitbox_misc_actions', array( $this, 'commonmark_parser_submit_box' ) );

        /* Post filters */
        remove_filter( 'the_content', 'wpautop' );
        add_filter( 'the_content', array( $this, 'commonmark_parser_convert' ), 8 );

        /* Excerpt filters */
        remove_filter( 'the_excerpt', 'wpautop' );
        // add_filter( 'the_excerpt', array( $this, 'commonmark_parser_convert' ), 8 );

        /* Comment filters */
        remove_filter( 'comment_text', 'wpautop' );
        add_filter( 'comment_text', array( $this, 'commonmark_parser_convert' ), 8 );
    }

    public function remove_profile_editor_options() {

        /* Remove editor option in user profile */
        ?>
        <style>.user-rich-editing-wrap, .user-syntax-highlighting-wrap {display: none;}</style>
        <?php
    }

    public function remove_default_quicktags( $qtInit ) {

        /* Removing default text editor buttons - Must be set to "," */
        $qtInit['buttons'] = ',';
        return $qtInit;
    }

    public function add_markdown_quicktags() {
        if ( wp_script_is( 'quicktags' ) ) {
        ?>
            <script language="javascript" type="text/javascript">

                var table = [
                    '| Header 1 | Header 2 | Header 3 | Header 4 |\n',
                    '|----------|:---------|:--------:|---------:|\n',
                    '| default | align left | align center | align right |'
                    ].join('');

                var attribute  = [
                    '> A nice blockquote\n',
                    '{: title="Blockquote title"}\n\n',
                    '{#id .class}\n',
                    '## Header\n\n',
                    'This is *red*{style="color: red"}\n\n',
                    'See - https://kramdown.gettalong.org/syntax.html#attribute-list-definitions'
                    ].join('');

                /* Adding Markdown Quicktag buttons to the editor WordPress ver. 3.3 and above
                 * - Button HTML ID (required)
                 * - Button display, value="" attribute (required)
                 * - Opening Tag (required)
                 * - Closing Tag (required)
                 * - Access key, accesskey="" attribute for the button (optional)
                 * - Title, title="" attribute (optional)
                 * - Priority/position on bar, 1-9 = first, 11-19 = second, 21-29 = third, etc. (optional)
                 */
                QTags.addButton( 'md-heading-1', 'h1', '# ' );
                QTags.addButton( 'md-heading-2', 'h2', '## ' );
                QTags.addButton( 'md-heading-3', 'h3', '### ' );
                QTags.addButton( 'md-bold', 'b', '**', '**' );
                QTags.addButton( 'md-italic', 'i', '*', '*' );
                QTags.addButton( 'md-strikethrough', 'del', '~~', '~~' );
                QTags.addButton( 'md-unordered-list', 'ul', '* ' );
                QTags.addButton( 'md-ordered-list', 'ol', '1. ' );
                QTags.addButton( 'md-horizontal-rule', 'hr', '---\n' );
                QTags.addButton( 'md-blockquote', 'q', '> ' );
                QTags.addButton( 'md_tables', 'tbl', table, '');
                QTags.addButton( 'md-link', 'link', '[Link](https://www.example.com)' );
                QTags.addButton( 'md-image', 'img', '![Image](image.jpg)' );
                QTags.addButton( 'md-code-inline', 'code inline', '`', '`' );
                QTags.addButton( 'md-code-block', 'code block', '```\n', '```\n' );
                QTags.addButton( 'md-prompt-block', 'prompt block', '```bash- (root user mysql)\n', '```\n' );
                QTags.addButton( 'md_attributes', 'attributes', attribute, '');
            </script>
        <?php
        }
    }

    public function enqueue_commonmark_parser_admin_styles() {
        wp_enqueue_style( 'commonmark_parser_css', plugins_url( 'assets/css/admin.css', __FILE__ ) );
    }

    public function enqueue_commonmark_parser_styles() {
        wp_enqueue_style( 'commonmark_parser_css', plugins_url( 'assets/css/commonmark-parser.css', __FILE__ ) );
    }

    public function commonmark_parser_submit_box() {

        /* Append to publish box */
        ?>
        <div class="misc-pub-section markdown"><span class="icon-markdown"></span><span>Markdown: <strong>CommonMark</strong></span></div>
        <?php
    }

    public function commonmark_parser_convert( $content ) {

				/* Obtain a pre-configured Environment with all the CommonMark parsers/renderers ready-to-go */
				$environment = Environment::createCommonMarkEnvironment();

				/* Register Extensions */
				$environment->addExtension( new GithubFlavoredMarkdownExtension() );
				$environment->addExtension( new AttributesExtension() );
				$environment->addExtension(new FootnoteExtension());

				/* Config */
				global $config;

				/* Now that the `Environment` is configured we can create the converter engine: */
        $converter = new CommonMarkConverter( $config, $environment );

        /* Process any [short-codes] first */
        $content = do_shortcode( $content );

				/* Convert */
        return $converter->convertToHtml( $content );
    }
}
$CommonMarkParser = new CommonMarkParser();
