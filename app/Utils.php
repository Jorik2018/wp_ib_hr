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
