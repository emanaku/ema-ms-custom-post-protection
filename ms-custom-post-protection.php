<?php
/**
* Plugin Name: [Membership] - Custom Post Protection
* Plugin URI: https://premium.wpmudev.org/
* Description: Custom Post Protection for Memebership
* Author: Panos Lyrakis @ WPMUDEV (with changes by Emanáku - ema)
* Author URI: https://premium.wpmudev.org/
* License: GPLv2 or later
*/
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPMUDEV_Custom_Post_Protection' ) ) {
    
    class WPMUDEV_Custom_Post_Protection {
        private static $_instance = null;
        static protected $denied_ids = array();
        static protected $default_featured_img_id = 82;
        public static function get_instance() {
            if( is_null( self::$_instance ) ){
                self::$_instance = new WPMUDEV_Custom_Post_Protection();
            }
            return self::$_instance;
            
        }
        private function __construct() {
            
            if ( ! class_exists( 'MS_Rule_Post_Model' ) ) {
                return;
            }
            add_action( 'pre_get_posts', array( $this, 'remove_protection_hooks' ), 98 );
            add_action( 'pre_get_posts', array( $this, 'find_protected_posts' ), 99 );
            add_filter( 'post_link', array( $this, 'filter_post_link' ), 20, 3 );
            add_filter( 'get_post_metadata', array( $this, 'filter_post_featured_image' ), 20, 4 );
        }
        public function filter_post_featured_image( $featured_image_id = null, $post_id, $meta_key, $single ) {
            if ( ( current_user_can( 'manage_options' )) || ('_thumbnail_id'  != $meta_key) ) {	// ema added test if user is admin
                return $featured_image_id;
            }
            // ema added test on get_post_type, because $denied_ids seem to provide the post_types you are not allowed to see
            if ( in_array( $post_id, self::$denied_ids  ) || in_array( get_post_type($post_id), self::$denied_ids  ) ) {
                $featured_image_id = self::$default_featured_img_id;
            }
            
            return $featured_image_id;
        }
        public function filter_post_link( $url, $post, $leavename ) {
        if ( current_user_can( 'manage_options' ) ){	// ema added test if user is admin
                return $url;
            }
            if ( in_array( $post->ID, self::$denied_ids )  || in_array( get_post_type($post->ID), self::$denied_ids  )  ) {
                $url = MS_Model_Pages::get_page_url( MS_Model_Pages::MS_PAGE_MEMBERSHIPS );
            }
            return $url;
        }
        public function remove_protection_hooks() {
            // Need to remove both:
            // MS_Rule_Post_Model::find_protected_posts() and
            // MS_Rule_Post_Model::protect_posts()
            global $wp_filter;
            $tag            = 'pre_get_posts';
            $hooks          = array( 'protect_posts', 'find_protected_posts' );
            $hooks_class    = 'MS_Rule_Post_Model';
            foreach ( $wp_filter[$tag]->callbacks as $key => $callback_array ) {
                foreach ( $callback_array as $c_key => $callback ) {
                    if ( 
                        $callback['function'][0] instanceof $hooks_class && 
                        in_array( $callback['function'][1], $hooks )
                    ){
                        unset( $wp_filter[$tag]->callbacks[$key][$c_key] );                        
                    }
                }
            }
        }
        public function find_protected_posts( $wp_query ) {
            // List rather than on a single post
            if ( ( ! $wp_query->is_singular
                && empty( $wp_query->query_vars['pagename'] )
                && ( ! isset( $wp_query->query_vars['post_type'] )
                    || in_array( $wp_query->query_vars['post_type'], array( 'post', '' ) )
                ) )
                || is_home()
            ) {
                $rules = $this->get_rules( array( 'post' , 'cpt_group') );	// ema added cpt_group
                foreach ( $rules as $membership_id => $rule ) {
                    foreach ( $rule as $type => $items ) {
                        foreach ( $items as $item ) {
                            self::$denied_ids[] = $item;
                        }
                    }
                }
            }
        }
        public function get_rules( $types = array() ) {
            $membership_ids = MS_Model_Membership::get_membership_ids();
            $ignored_memberships = $this->user_memberships();            
            $rules = array();
            foreach ( $membership_ids as $membership_id ) {
                // Ignore the rules that are about Memberships for which member has already subscribed to
                if ( in_array( $membership_id, $ignored_memberships ) ) {
                    continue;
                }
                $membership_rules = get_post_meta( $membership_id, 'rule_values', true );
                 if ( empty( $types ) ) {
                    $rules[ $membership_id ] = $membership_rules;
                }
                else {
                    foreach ( $types as $type ) {
                        if ( isset( $membership_rules[ $type ] ) ) {
                            $rules[ $membership_id ][ $type ] = $membership_rules[ $type ];
                        }
                    }
                }
                
            }
            
            return $rules;
        }
        public function user_memberships() {
            if ( ! is_user_logged_in() ) {
                return array();
            }
            $membership_ids = array();
            $allowed_subscription_statuses = array(
                                                    MS_Model_Relationship::STATUS_ACTIVE,
                                                    MS_Model_Relationship::STATUS_TRIAL
                                                );
            $member = MS_Model_Member::get_current_member();
            foreach ( $member->subscriptions as $subscription ) {
                if ( in_array( $subscription->status, $allowed_subscription_statuses ) ) {
                    $membership_ids[] = $subscription->membership_id;
                }
            }
        return $membership_ids;
        }
    }
    if ( ! function_exists( 'wpmudev_custom_post_protection' ) ) {
        function wpmudev_custom_post_protection(){            
            return WPMUDEV_Custom_Post_Protection::get_instance();
        };
        add_action( 'plugins_loaded', 'wpmudev_custom_post_protection', 10 );
    }
}