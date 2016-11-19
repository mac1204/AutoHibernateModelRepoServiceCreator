<?php
$server_ip = readline("server ip: ");
$server_username = readline("server username: ");
$server_password = readline("server password: ");
$database = readline("name the database: ");
$table = readline("name the table name for which you want to create hibernate model: ");
$db2 = mysql_pconnect($server_ip,$server_username,$server_password,false);
        mysql_select_db($database,$db2) or die(mysql_error());
createModel($table, $db2);
function createModel($table, $db2) {
$re = mysql_query("show create table ".$table,$db2);
$row = mysql_fetch_row($re);
//print_r($row['1']);

$arr = explode("\n", $row['1']);
//var_dump($arr);die();
$arr1 = explode(" ", $arr['0']);
$table_name = str_replace("`","",$arr1['2']);
$arr1 = explode(" ", $arr['1']);
$columnArray = Array();
$isNotNullArray = Array();
$i = 2;
while($arr1['2'] != 'PRIMARY'){
	if(preg_match('/int/',$arr1['3'])){
		if(strlen($arr1['3'])<=6 || preg_match('/tinyint/',$arr1['3']) || preg_match('/mediumint/',$arr1['3']) || preg_match('/smallint/',$arr1['3']) ) {
			$columnArray[str_replace("`","",$arr1['2'])] = "Integer";
		} else {
			$columnArray[str_replace("`","",$arr1['2'])] = "Long";
		}
	} else if(preg_match('/enum/',$arr1['3']) || preg_match('/varchar/',$arr1['3']) || preg_match('/text/',$arr1['3'])) {
		$columnArray[str_replace("`","",$arr1['2'])] = "String";
	} else if(preg_match('/date/',$arr1['3']) || preg_match('/time/',$arr1['3'])) {
		$columnArray[str_replace("`","",$arr1['2'])] = "Timestamp";
	} else if(preg_match('/decimal/',$arr1['3'])){
		$columnArray[str_replace("`","",$arr1['2'])] = "Double";
	}
	if(preg_match('/NOT/',$arr1['4']) && preg_match('/NULL/',$arr1['5'])) {
		$isNotNullArray[str_replace("`","",$arr1['2'])] = true;
	}else {
		$isNotNullArray[str_replace("`","",$arr1['2'])] = false;
	}
	$arr1 = explode(" ", $arr[$i]);
	$i++;
}


$primary_key = str_replace("`),","",$arr1['4']);
$primary_key = str_replace("(`","",$primary_key);
//print_r($primary_key);
//var_dump($columnArray);

$keyArray = Array();
$j = 0;
//print_r($arr);
//print_r($arr1);
//die();
//echo $arr1['2'];die();
while($arr1['2'] != 'CONSTRAINT' && !preg_match('/ENGINE/',$arr1['1'])){
	if($arr1['2'] == 'PRIMARY'){
		$keyArrayTemp = str_replace("`),","",$arr1['4']);
		$keyArrayTemp = str_replace("(`","",$keyArrayTemp);
		$keyArray[str_replace("`","",$keyArrayTemp)] = "UNIQUE";
	} else if($arr1['2'] == 'UNIQUE') {
		$keyArrayTemp = str_replace("`),","",$arr1['5']);
		$keyArrayTemp = str_replace("(`","",$keyArrayTemp);
		$keyArray[str_replace("`","",$keyArrayTemp)] = "UNIQUE";
	} else if($arr1['2'] == 'KEY') {
		if( strpos($arr1['4'],"`),") !== false ) {
			$keyArrayTemp = str_replace("`),","",$arr1['4']);
		} else {
			$keyArrayTemp = str_replace("`)","",$arr1['4']);
		}
		$keyArrayTemp = str_replace("(`","",$keyArrayTemp);
		if(!array_key_exists(str_replace("`","",$keyArrayTemp), $keyArray)) {
			$keyArray[str_replace("`","",$keyArrayTemp)] = 1;
		}
	}
	$arr1 = explode(" ", $arr[$i]);
	$i++;
	$j++;
}


$model_header = "package com.mac.model;\n\nimport java.io.Serializable;\n";
if(in_array("Timestamp",$columnArray)){
$model_header.= "import java.sql.Timestamp;\n";
}
$model_header.= "\nimport javax.persistence.Column;\nimport javax.persistence.Entity;\nimport javax.persistence.Id;\nimport javax.persistence.Table;\n\n
import org.hibernate.validator.constraints.NotBlank;\n\n@Entity\n@Table(name = \"". $table_name ."\")\npublic class " . snakeToCamelCase($table_name,true) . " implements Serializable {\n";


$model_footer = "}";


$repository_header = "package com.mac.dao;\n\nimport java.util.List;\n";
if(in_array("Timestamp",$columnArray)){
$repository_header.= "import java.sql.Timestamp;\n";
}

$repository_header.= "\nimport org.springframework.data.repository.CrudRepository;\nimport com.mac.model.".snakeToCamelCase($table_name,true).";\n\npublic interface " . snakeToCamelCase($table_name,true) . "Repository extends CrudRepository<".snakeToCamelCase($table_name,true).",".$columnArray[$primary_key].">{\n";



$repository_footer = "}";


$variable = "";
foreach($columnArray as $x => $x_value) {
    if($x_value == "String" && $isNotNullArray[$x]) {
	$variable.= "\t@NotBlank\n";
    }
    $variable.= "\tprivate " . $x_value . " " . snakeToCamelCase($x) . ";\n";
}


$geter = "";
foreach($columnArray as $x => $x_value) {
    if($x == $primary_key){
	$geter .= "\t@Id\n";
    }
    $geter.= "\t@Column(name = \"".$x."\")\n\tpublic " . $x_value . " get" . snakeToCamelCase($x,true) . "() {\n\t\treturn this.".snakeToCamelCase($x).";\n\t}\n";
}


$seter = "";
foreach($columnArray as $x => $x_value) {
    $seter.= "\tpublic void set" . snakeToCamelCase($x,true) . "(".$x_value." ".snakeToCamelCase($x).") {\n\t\tthis.".snakeToCamelCase($x)." = ".snakeToCamelCase($x).";\n\t}\n";
}

$finder = "";
foreach($keyArray as $x => $x_value) {
    $subKeyArray = explode(",",$x);
	//print_r($subKeyArray);die();
    $capatalizeNonTypeString = "";
    $noncapatalizeWithTypeString = "";
    foreach($subKeyArray as $y => $y_value){
	//echo $y;
	//echo $y_value;
	if($capatalizeNonTypeString != ""){
		$capatalizeNonTypeString.= "And";
	}
	$capatalizeNonTypeString.= snakeToCamelCase($y_value,true);
	if($noncapatalizeWithTypeString != ""){
		$noncapatalizeWithTypeString.= ", ";
	}
	$noncapatalizeWithTypeString.= $columnArray[$y_value]. " " .snakeToCamelCase($y_value);
    }
    if($x_value == "UNIQUE"){
	$finder.= "\tpublic ".snakeToCamelCase($table_name,true)." findOneBy" . $capatalizeNonTypeString . "(" . $noncapatalizeWithTypeString . ");\n";
    } else {
    	$finder.= "\tpublic List<".snakeToCamelCase($table_name,true)."> findAllBy" . $capatalizeNonTypeString . "(" . $noncapatalizeWithTypeString . ");\n";
    }
}

//die();

//echo $model_header;
//echo $variable;
//echo $geter;
//echo $seter;
//echo $model_footer;

$fileModel = fopen(snakeToCamelCase($table_name,true).".java", "w") or die("Unable to open file!");
$txt = $model_header;
$txt .= $variable;
$txt .= $geter;
$txt .= $seter;
$txt .= $model_footer;
fwrite($fileModel, $txt);
fclose($fileModel);


$fileRepo = fopen(snakeToCamelCase($table_name,true)."Repository.java", "w") or die("Unable to open file!");
$txt = $repository_header;
$txt .= $finder;
$txt .= $repository_footer;
fwrite($fileRepo, $txt);
fclose($fileRepo);
}


function snakeToCamelCase($string, $capitalizeFirstCharacter = false) 
{

    $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

    if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
    }

    return $str;
}
?>

