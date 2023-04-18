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

    public function get_employee()
    {

    }

    public function init()
    {
        register_rest_route( 'api/rh','/employee', array(
            'methods' => 'GET',
            array($this,'get_employee')
        ));
    }
    
}