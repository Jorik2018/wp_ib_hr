<?php

namespace IB\cv\Controllers;

use WPMVC\MVC\Controller;
/**
 * MyController
 * WordPress MVC controller.
 *
 * @author me
 * @package ip-cv
 * @version 1.0.0
 */
class CvRestController extends Controller
{

    public function employee_get()
    {

    }

    public function employee_pag()
    {

    }

    public function study_get()
    {

    }

    public function study_pag()
    {
        return 1;
    }

    public function training_get()
    {

    }

    public function training_pag()
    {

    }

    public function experience_get()
    {

    }

    public function experience_pag()
    {

    }

    public function init()
    {
        register_rest_route( 'api/hr','/employee/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            array($this,'employee_pag')
        ));

        register_rest_route( 'api/hr','/employee/(?P<id>\d+)', array(
            'methods' => 'GET',
            array($this,'employee_get')
        ));

        register_rest_route( 'api/hr','/study/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'study_pag')
        ));

        register_rest_route( 'api/hr','/study/(?P<id>\d+)', array(
            'methods' => 'GET',
            array($this,'study_get')
        ));

        register_rest_route( 'api/hr','/training/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'training_pag')
        ));

        register_rest_route( 'api/hr','/training/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this,'training_get')
        ));

        register_rest_route( 'api/hr','/experience/(?P<from>\d+)/(?P<to>\d+)', array(
            'methods' => 'GET',
            array($this,'experience_pag')
        ));

        register_rest_route( 'api/hr','/experience/(?P<id>\d+)', array(
            'methods' => 'GET',
            array($this,'experience_get')
        ));
    }
    
}