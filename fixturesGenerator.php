<?php

include("$rel/include/dbconnect.inc.php");
include("./config.php");

ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);

$fixtures = '';

$recountData = array();

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
    global $defaultColumnsData, $recountData;

    $limit = $filterData['limit'];
    $order = $filterData['order'];

    $select = "SELECT * from $table";

    $selCondition = $collectionColumn = '';

    $conjunction = false;

    foreach ($filterData['filterCondition'] as $fData) {
        $conjunction = $conjunction === false ? '' : $conjunction = $fData['conjunction'] ?: ' AND ';

        $fTable = $fData['filterTable'];

        if ($fData['val']) {
            $filterCondition = $fData['val'];
        } else {
            $collectionColumn = $fData['collectCol'];
            $filterColToRecount = $fData['filterCol'];//TODO, do this -> works only for ONE -> it is used for recounting ids
            $filterTable        = $fTable;
            $filterCondition = $dependData[$fTable][$collectionColumn];
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

    $recountVal = 1000;

    while($row = fktfetcharray($query, MYSQLI_ASSOC)) {
        $addFixtures .= '  -' ."\n";

        // NOTE: keep order in this foreach,  must save recount column,then replace the id -> with new Recount value,  then use default
        foreach ($row as $colName => $colData) {

            if (in_array($colName, $collect)) {
                $collectedData[$colName][] = $colData;
            }

            if (in_array($colName, $filterData['recountCol'])) {
                if(array_key_exists($table, $recountData) && array_key_exists($colName, $recountData[$table])) {
                    $recountVal = $recountData[$table][$colName]['countValue'];
                }

                // NOTE:  the colName in path does not allow for  table with  id, type, ref -> the ref start from 1001 for each type
                $recountData[$table][$colName][$colData] = $recountVal++;
                $recountData[$table][$colName]['countValue'] = $recountVal;

                $colData = $recountData[$table][$colName][$colData];
            } else if ($colName == $filterColToRecount) {

                //[TODO -> bug v tabulce land   (nr neodpovida odkazu v zusatz_data  val
                if ($table == 'land') {
                    var_dump($recountData[$filterTable]);
                    var_dump(sprintf("\033[41m.....\033[0m%s: ", "colData - colName").var_export($colName, 1));
                    var_dump(sprintf("\033[41m.....\033[0m%s: ", "colData - collectionColumn").var_export($collectionColumn, 1));
                }

                //$colData = $recountData[$filterTable][$colName][$colData];
                $colData = $recountData[$filterTable][$collectionColumn][$colData];
            }

            if (false && $table == 'land') {
                var_dump(sprintf("\033[41m.....\033[0m%s: ", "filterColToRecount").var_export($filterColToRecount, 1));
                var_dump(sprintf("\033[41m.....\033[0m%s: ", "recountData").var_export($recountData, 1));
                var_dump(sprintf("\033[41m.....\033[0m%s: ", "filterTable").var_export($filterTable, 1));
                var_dump(sprintf("\033[41m.....\033[0m%s: ", "collectionColumn").var_export($collectionColumn, 1));
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

