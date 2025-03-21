<?php

/* file: app/Util/Utils.php */

namespace IB\cv\Util;

use WPMVC\Bridge;

function toCamelCase($data) {
    if (is_object($data)) {
        $result = new \stdClass();
        foreach ($data as $key => $value) {
            $newKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            $result->$newKey = toCamelCase($value);
        }
        return  $result;
    } elseif (is_array($data)) {
		$keys = array_keys($data);
		$isNumeric = empty($keys);
        if(!$isNumeric )
		foreach ($keys as $key) {
			if (is_int($key)) {
				$isNumeric = true;
			}
			break;
		}
		if($isNumeric){
			$result = array();
			foreach ($data as $item) {
				$result[] = toCamelCase($item);
			}
			return $result;
		}else{
			$result = new \stdClass();
			foreach ($data as $key => $value) {
				$newKey = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
				$result->$newKey = $value;
			}
			return  $result;
		}
    } else {
        return $data;
    }
}

function cdfield(&$row,$key){
    if(is_numeric($row[$key])){
        $row[$key]=date("Y-m-d",$row[$key]/1000);
    }
    return $row;
}

function cbfield(&$row,$key){
    if(is_numeric($row[$key])){
		$v=$row[$key];
		unset($row[$key]);
        $row[$key]=intval($v)>0;
    }
    return $row;
}

function cdfield2(&$row,$key){
    if(is_numeric($row[$key])){
        $row[$key]=date("Y-m-d H:i:s",$row[$key]/1000);
    }
    return $row;
}

function get_param($request, $param_name) {
    if (is_object($request) && method_exists($request, 'get_param')) {
        return $request->get_param($param_name);
    }
    return isset($request[$param_name]) ? $request[$param_name] : null;
}

function cfield(&$row,$from,$to){
    if(array_key_exists($from,$row)){
        $row[$to]=$row[$from];
        unset($row[$from]);
    }
    return $row;
}

function remove(array &$arr, $key) {
    if (array_key_exists($key, $arr)) {
        $val = $arr[$key];
        unset($arr[$key]);
        return $val;
    }
    return null;
}

function t_error($msg=false){
    global $wpdb;
    $error=new WP_Error(500,$msg?$msg:$wpdb->last_error, array('status'=>500));
    $wpdb->query('ROLLBACK');
    return $error;
}

