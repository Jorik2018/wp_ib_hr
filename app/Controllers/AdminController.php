<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
/**
 * AdminController
 * WordPress MVC controller.
 *
 * @author 
 * @package ib-cv
 * @version 1.0.0
 */
class AdminController extends Controller
{
    public function display_hello_world_page() {
        /*$view = $this->view->get('view.hello-world');
        // View is prrinted
        // Array of parameters ar passed to the view
        $this->view->show('view.hello-world', [
            'param1' => true,
            'model'  => MyModel::find()
        ]);*/
       // $view = $this->view->get( 'hello-world' );
        // Print a view
        //$this->view->show( 'hello-world' );
       
        //$view = $this->view->get( 'hello-world' );
        // Print a view
        if ( $this->user ) {
            $this->view->show( 'hello-world', [
                'display_name' => $this->user->display_name,
                'email'        => $this->user->user_email,
                'people'=>['id'=>77,"name"=>"OOOO"]
            ] );
        }
        //return $this->view->get( 'hello-world' );
        //return $this->view->get( 'views.hello-world' );
    }

    public function init()
    {
        add_menu_page(
            'Hello World',// page title
            'Hello World',// menu title
            'manage_options',// capability
            'hello-world',// menu slug
            array($this,'display_hello_world_page') // callback function
        );
    }

    function jal_install() {
        global $wpdb;
        global $jal_db_version;
    
        $table_name = $wpdb->prefix . 'liveshoutbox';
        
        $charset_collate = $wpdb->get_charset_collate();
    
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            name tinytext NOT NULL,
            text text NOT NULL,
            url varchar(55) DEFAULT '' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
    
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
        //https://codex.wordpress.org/Creating_Tables_with_Plugins
        add_option( 'jal_db_version', $jal_db_version );
    }

    function activate() {
        global $jal_db_version;
        if ( get_site_option( 'jal_db_version' ) != $jal_db_version ) {
            $this->jal_install();
        }
    }

}