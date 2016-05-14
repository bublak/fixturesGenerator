<?php

include("$rel/include/dbconnect.inc.php");
include("./config.php");

ini_set('xdebug.var_display_max_depth', -1);
ini_set('xdebug.var_display_max_children', -1);
ini_set('xdebug.var_display_max_data', -1);

$fixtures = '';

$recountData = array();

const MAX_COUNT_LIMIT = 100;
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

    //clean depend data -> are used for recursion -> use default definition
    $recountData = array();

    return $fixtures;
}

//[TODO, , B] split big function
function doSelect ($table, $filterData, &$fixtures, $collect, $dependData=array()) {
    global $defaultColumnsData, $recountData;

    $limit = $filterData['limit'];
    $order = $filterData['order'];

    $select = "SELECT * from $table";

    $selCondition = $collectionColumn = '';

    $conjunction = false;

    if ($filterData['filterCondition'] === false) {
        if ($newLimit = _checkMaxCountLimit($table)) {
            $limit =  $newLimit;
        }
    } else {
        foreach ($filterData['filterCondition'] as $fData) {
            $conjunction = $conjunction === false ? '' : $conjunction = $fData['conjunction'] ?: ' AND ';

            $fTable = $fData['filterTable'];

            if ($fData['val']) {
                $filterCondition = $fData['val'];
            } else {
                $collectionColumn = $fData['collectCol'];
                $filterColToRecount = $fData['filterCol'];////[TODO,  B] do this -> works only for ONE -> it is used for recounting ids
                $filterTable        = $fTable;
                $filterCondition = $dependData[$fTable][$collectionColumn];
            }

            $filterConditionResult = '';

            if (is_array($filterCondition)) {
                $filterCondition = array_filter($filterCondition);
            }

            if (empty($filterCondition)) {
                echo "WARNING: Expected filter condition, but none received.";

                if ($newLimit = _checkMaxCountLimit($table)) {
                    $limit = $newLimit;
                }
            } else {
                $filterConditionResult = $fData['filterCol'] . " IN (".implode(',', $filterCondition).") ";

                $selCondition.= $conjunction.  " " . $filterConditionResult;
            }
        }
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
    $query  = fktquery($select, $rel);

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
                if(isset($recountData[$table][$colName])) {
                    $recountVal = $recountData[$table][$colName]['countValue'];
                }

                // NOTE:  the colName in path does not allow for  table with  id, type, ref -> the ref start from 1001 for each type
                if (!isset($recountData[$table][$colName][$colData])) {
                    // to prevent recounting the same values in table
                    $recountData[$table][$colName][$colData] = $recountVal++;
                    $recountData[$table][$colName]['countValue'] = $recountVal;
                }

                $colData = $recountData[$table][$colName][$colData];
            } else if (array_key_exists($colName, $filterData['recountCol'])) {
                // if it is defined in key, it means, that the recounted value is taken from others table
                //   column, which was recounted
                $definedRecountedData    = $filterData['recountCol'][$colName];
                $tableForRecount  = array_keys($definedRecountedData)[0];
                $columnForRecount = array_values($definedRecountedData)[0];

                if(isset($recountData[$tableForRecount][$columnForRecount])) {
                    $colData = $recountData[$tableForRecount][$columnForRecount][$colData];
                }
            } else if ($colName == $filterColToRecount) {
                //$colData = $recountData[$filterTable][$colName][$colData];
                $colData = $recountData[$filterTable][$collectionColumn][$colData];
            }

            if ((is_null($colData) || $colData == '') && in_array($colName, $defaultColumnsData[$table])) {
                $colData = '"default_'. $colName .'"';
            }

            $addFixtures .= '    ' . $colName . ': ' . $colData . "\n";
        }
    }

    $fixtures .= $addFixtures;

    ////[TODO,  B] do this ->  prvky nejsou uniq!
    return $collectedData;
}

function _checkMaxCountLimit($table) {
    $limit = false;

    $countSql = "select count(*) as count from $table";
    $count = fktquery($countSql, $rel);

    if (fktfetcharray($count)['count'] > MAX_COUNT_LIMIT) {
        echo "WARNING: MAX_COUNT_LIMIT " . MAX_COUNT_LIMIT . " protection added.";
        $limit = MAX_COUNT_LIMIT;
    }

    return $limit;
}

die('tady ne');

echo 'konec';

