<?php
/**
 * STI integrations
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'STI_Integrations' ) ) :

    /**
     * Class for main plugin functions
    */
    class STI_Integrations {

        /**
         * Return a singleton instance of the current class
         *
         * @return object
         */
        public static function factory() {
            static $instance = false;

            if ( ! $instance ) {
                $instance = new self();
                $instance->setup();
            }

            return $instance;
        }

        /**
         * Placeholder
         */
        public function __construct() {}

        /**
         * Setup actions and filters for all things settings
         */
        public function setup() {

            $this->includes();

            // Metaslider plugin
            add_filter( 'metaslider_flex_slider_parameters', array( $this, 'metaslider_flex_slider_parameters' ) );
            //add_filter( 'metaslider_nivo_slider_parameters', array( $this, 'metaslider_nivo_slider_parameters' ) );

            // Photo Gallery plugin
            if ( class_exists( 'BWG' ) ) {
                add_action( 'wp_enqueue_scripts', array( $this, 'bwg_wp_enqueue_scripts' ), 9999999 );
            }

        }

        /**
         * Include files
         */
        public function includes() {

            // Gutenberg block
            if ( function_exists( 'register_block_type' ) ) {
                include_once( STI_DIR . '/includes/modules/gutenberg/class-sti-gutenberg-init.php' );
            }

        }

        /*
         * Metaslider flex slider integration
         */
        public function metaslider_flex_slider_parameters( $options ) {

            $settings = $this->get_settings();

            $options['after'] = 'function(slider){ $("'. esc_html( stripslashes( $settings['selector'] ) ) .'").sti(); }';

            return $options;
        }

        /*
        * Metaslider nivo slider integration
        */
        public function metaslider_nivo_slider_parameters( $options ) {

            $settings = $this->get_settings();

            $options['afterChange'] = 'function(){ $("'. esc_html( stripslashes( $settings['selector'] ) ) .', .nivo-main-image").sti(); }';

            return $options;

        }

        /*
         * Photo Gallery plugin
         */
        public function bwg_wp_enqueue_scripts() {

            $script = "
                document.addEventListener('stiLoaded', function() {
                
                    function bwg_sti_share_container( el ) {
                      if ( el.closest('.bwg-item').length > 0 ) {
                          el = false;
                      }
                      return el;
                    }
                    StiHooks.add_filter( 'sti_share_container', bwg_sti_share_container );
                  
                    var timeoutID;
                      jQuery('body').on('DOMSubtreeModified', '#spider_popup_wrap', function() {
                        window.clearTimeout(timeoutID);
                        timeoutID = window.setTimeout( function() {
                            jQuery('.bwg_popup_image').sti( { 'position' : 'image' } );
                        }, 1000 );
                    });
                
                }, false);
            ";

            wp_add_inline_script( 'sti-script', $script);

        }

        /*
         * Register plugin settings
         */
        public function get_settings( $id = false ) {
            $sti_options = get_option( 'sti_settings' );
            if ( $id ) {
                return $sti_options[ $id ];
            } else {
                return $sti_options;
            }
        }

    }

endif;

add_action( 'init', 'STI_Integrations::factory' );