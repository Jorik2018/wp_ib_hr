<?php

namespace IB\cv;

use WPMVC\Bridge;

function toCamelCase($data) {
    if (is_array($data)) {
        $result = array();
        foreach ($data as $item) {
            $result[] = toCamelCase($item);
        }
        return $result;
    } elseif (is_object($data)) {
        $result = new stdClass();
        foreach ($data as $key => $value) {
            $newKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            $result->$newKey = toCamelCase($value);
        }
        return $result;
    } else {
        return $data;
    }
}

class Main extends Bridge
{
    public function api_covid(){
        return 2;
    }

    public function return_view()
    {
        return $this->mvc->view->get( 'view.key' );
    }

    public function init()
    {
        $this->add_action( 'rest_api_init','RestController@init' );
        $this->add_action( 'rest_api_init','EmployeeRestController@init');
        $this->add_action( 'rest_api_init','StudyRestController@init');
        $this->add_action( 'rest_api_init','TrainingRestController@init');
        $this->add_action( 'rest_api_init','ExperienceRestController@init');
        $this->add_action( 'plugins_loaded', 'AdminController@activate' );
    }

    public function on_admin()
    {
        $this->add_action('admin_menu', 'AdminController@init');
    }
}