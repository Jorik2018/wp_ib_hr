<?php

/* file: app/Util/Utils.php */

namespace IB\cv\Util;

use WPMVC\Bridge;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function export_excel($filename, $columns, $rows)
{
    while (ob_get_level()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Headers
    $col = 'A';
    foreach ($columns as $header) {
        $sheet->setCellValue($col . '1', $header);
        $col++;
    }

    // Data
    $rowNum = 2;
    foreach ($rows as $row) {
        $col = 'A';
        foreach ($columns as $key) {
            $sheet->setCellValue($col . $rowNum, $row[$key] ?? "");
            $col++;
        }
        $rowNum++;
    }

    // Headers CORS
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Authorization, Content-Type");

    // Excel output headers
    header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
    header("Content-Disposition: attachment; filename=\"{$filename}.xlsx\"");
    header("Cache-Control: max-age=0");

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
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
