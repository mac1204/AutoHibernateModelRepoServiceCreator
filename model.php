<?php
$server_ip = readline("server ip: ");
$server_username = readline("server username: ");
$server_password = readline("server password: ");
$database = readline("name the database: ");
$table = readline("name the table name for which you want to create hibernate model: ");
$db2 = mysql_pconnect($server_ip,$server_username,$server_password,false);
        mysql_select_db($database,$db2) or die(mysql_error());
$re = mysql_query("show create table ".$table,$db2);
$row = mysql_fetch_row($re);
//print_r($row['1']);

$arr = explode("\n", $row['1']);
//var_dump($arr);
$arr1 = explode(" ", $arr['0']);
$table_name = str_replace("`","",$arr1['2']);
$arr1 = explode(" ", $arr['1']);
$columnArray = Array();
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
	$arr1 = explode(" ", $arr[$i]);
	$i++;
}


$primary_key = str_replace("`),","",$arr1['4']);
$primary_key = str_replace("(`","",$primary_key);
//print_r($primary_key);
//var_dump($columnArray);


$model_header = "import java.io.Serializable;\n";
if(in_array("Timestamp",$columnArray)){
$model_header.= "import java.sql.Timestamp;\n";
}
$model_header.= "\nimport javax.persistence.Column;\nimport javax.persistence.Entity;\nimport javax.persistence.Id;\nimport javax.persistence.Table;\n\n@Entity\n@Table(name = \"". $table_name ."\")\npublic class " . snakeToCamelCase($table_name,true) . " implements Serializable {\n";


$model_footer = "}";


$variable = "";
foreach($columnArray as $x => $x_value) {
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

//echo $model_header;
//echo $variable;
//echo $geter;
//echo $seter;
//echo $model_footer;

$file = fopen(snakeToCamelCase($table_name,true).".java", "w") or die("Unable to open file!");
$txt = $model_header;
$txt .= $variable;
$txt .= $geter;
$txt .= $seter;
$txt .= $model_footer;
fwrite($file, $txt);
fclose($file);


function snakeToCamelCase($string, $capitalizeFirstCharacter = false) 
{

    $str = str_replace(' ', '', ucwords(str_replace('_', ' ', $string)));

    if (!$capitalizeFirstCharacter) {
        $str[0] = strtolower($str[0]);
    }

    return $str;
}
?>

