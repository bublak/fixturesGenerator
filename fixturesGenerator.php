<?php

include("$rel/include/dbconnect.inc.php");
include("./config.php");

ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);

$fixtures = '';

$fixtures = createFixtures($tableData, $defaultColumnsData, $startDependData);

echo '<br><br><br>';
var_dump($fixtures);

function createFixtures($tableData, $defaultColumnsData, $dependData=null) {
    $fixtures = '';

    foreach ($tableData as $filterData) {
        $table = $filterData['table'];

        $collectColumns = array();
        if (array_key_exists('collect', $filterData)) {
            $collectColumns = $filterData['collect'];
        }

        $dependData[$table] = doSelect($table, $filterData, $fixtures, $collectColumns, $dependData);

        if ($filterData['hierarchy']) {
            $fixtures .= createFixtures($filterData['hierarchy'], $defaultColumnsData, $dependData);
        }

    }

    //clean depend data -> are used for recursion
    unset($dependData);

    return $fixtures;
}

function doSelect ($table, $filterData, &$fixtures, $collect, $dependData=array()) {
    global $defaultColumnsData;

    $limit = $filterData['limit'];
    $order = $filterData['order'];

    $select = "SELECT * from $table";

    $selCondition = '';

    $conjunction = false;

    foreach ($filterData['filterCondition'] as $fData) {
        $conjunction = $conjunction === false ? '' : $conjunction = $fData['conjunction'] ?: ' AND ';

        $fTable = $fData['filterTable'];

        if ($fData['val']) {
            $filterCondition = $fData['val'];
        } else {
            $filterCondition = $dependData[$fTable][$fData['collectCol']];
        }

        $selCondition.= $conjunction.  " ".$fData['filterCol']." IN (".implode(',', $filterCondition).") ";
    }

    if ($selCondition) {
        $select .= ' where ' . $selCondition;
    }

    if ($order) {
        $select.= " order by $order[0] $order[1]";
    }

    if ($limit) {
        $select.= " limit $limit";
    }

    var_dump(sprintf("\033[41m.....\033[0m%s: ", "select").var_export($select, 1));
    $query  = fktquery($select,$rel);

    $addFixtures = $table.':';
    $addFixtures .= "\n";

    $collectedData = null;

    if (!array_key_exists($table, $defaultColumnsData)) {
        $defaultColumnsData[$table] = array();
    }

    while($row = fktfetcharray($query, MYSQLI_ASSOC)) {
        $addFixtures .= '  -' ."\n";

        foreach ($row as $colName => $colData) {
            if (in_array($colName, $collect)) {
                $collectedData[$colName][] = $colData;
            }

            if ((is_null($colData) || $colData == '') && in_array($colName, $defaultColumnsData[$table])) {
                $colData = '"default_'. $colName .'"';
            }

            $addFixtures .= '    ' . $colName . ': ' . $colData . "\n";
        }
    }

    $fixtures .= $addFixtures;

    return $collectedData;
}

echo 'konec';

