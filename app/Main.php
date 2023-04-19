<?php

namespace IB\cv;

use WPMVC\Bridge;
/**
 * Main class.
 * Bridge between WordPress and App.
 * Class contains declaration of hooks and filters.
 *
 * @author 
 * @package ib-cv
 * @version 1.0.0
 */
class Main extends Bridge
{
    public function api_covid(){
        return 2;
    }

    public function return_view()
    {
        return $this->mvc->view->get( 'view.key' );
    }
    /**
     * Declaration of public WordPress hooks.
     */
    public function init()
    {
        //$this->add_filter( 'the_content', 'MyController@print_hello_world' );
        $this->add_action( 'rest_api_init','RestController@init' );
        $this->add_action( 'rest_api_init','CvRestController@init');
        $this->add_action( 'plugins_loaded', 'AdminController@activate' );
    }
    /**
     * Declaration of admin only WordPress hooks.
     * For WordPress admin dashboard.
     */
    public function on_admin()
    {
        $this->add_action('admin_menu', 'AdminController@init');
    }
}