<?php
$server_ip = readline("server ip: ");
$server_username = readline("server username: ");
$server_password = readline("server password: ");
$database = readline("name the database: ");
$table = readline("name the table name for which you want to create hibernate model: ");
$basePackage = readline("Enter the base package name: ");
$projectName = readline("Enter the project name: ");
$db2 = mysql_pconnect($server_ip,$server_username,$server_password,false);
        mysql_select_db($database,$db2) or die(mysql_error());
$packageArray = explode(".",$basePackage);
$packagePathString = str_replace(".","/",$basePackage);
makeDir($projectName);
makeDir($projectName."/src");
makeDir($projectName."/src/main");
makeDir($projectName."/src/main/java");
$path = $projectName."/src/main/java/";
foreach($packageArray as $x => $x_value) {
	$path.= $x_value;
	makeDir($path);
	echo str_replace("/",".",$path)." package created.\n";
	$path.= "/";
}

function makeDir($path)
{
     return is_dir($path) || mkdir($path);
}

createModel($table, $db2, $basePackage, $path);


function createModel($table, $db2, $basePackage, $path) {
makeDir($path."service");
makeDir($path."model");
makeDir($path."controller");
makeDir($path."dao");
makeDir($path."service/impl");

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
while($arr1['2'] != 'PRIMARY' && $arr1['2'] != 'CONSTRAINT' && !preg_match('/ENGINE/',$arr1['1'])){
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


$model_header = "package ".$basePackage.".model;\n\nimport java.io.Serializable;\n";
if(in_array("Timestamp",$columnArray)){
$model_header.= "import java.sql.Timestamp;\n";
}
$model_header.= "\nimport javax.persistence.Column;\nimport javax.persistence.Entity;\nimport javax.persistence.Id;\nimport javax.persistence.Table;\n\n
import org.hibernate.validator.constraints.NotBlank;\n\n@Entity\n@Table(name = \"". $table_name ."\")\npublic class " . snakeToCamelCase($table_name,true) . " implements Serializable {\n";


$model_footer = "}";


$repository_header = "package ".$basePackage.".dao;\n\nimport java.util.List;\n";
if(in_array("Timestamp",$columnArray)){
$repository_header.= "import java.sql.Timestamp;\n";
}

$repository_header.= "\nimport org.springframework.data.domain.Page;\nimport org.springframework.data.domain.Pageable;\nimport org.springframework.data.repository.CrudRepository;\nimport ".$basePackage.".model.".snakeToCamelCase($table_name,true).";\n\npublic interface " . snakeToCamelCase($table_name,true) . "Repository extends CrudRepository<".snakeToCamelCase($table_name,true).",".$columnArray[$primary_key].">{\n";



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

$serviceImplUniqueGet = Array();
$UniqueConatinsType = Array("String"=>false, "Long"=>false, "Double"=>false);
$finder = "";
$finder.= "\tpublic Page<".snakeToCamelCase($table_name,true)."> findAll(Pageable pageable);\n";
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
    	if (strpos($x, ',') === false) {
    		if($columnArray[$x] == "String"){
    			$UniqueConatinsType["String"] = true;
    			$serviceImplUniqueGet["findOneBy".snakeToCamelCase($x,true)."(code);"] = "String";
    		} else if($columnArray[$x] == "Long"){
    			$UniqueConatinsType["Long"] = true;
    			$serviceImplUniqueGet["findOneBy".snakeToCamelCase($x,true)."(Long.parseLong(code));"] = "Long";
    		} else if($columnArray[$x] == "Double"){
    			$UniqueConatinsType["Double"] = true;
    			$serviceImplUniqueGet["findOneBy".snakeToCamelCase($x,true)."(Double.parseDouble(code));"] = "Double";
    		}
    	}
	$finder.= "\tpublic ".snakeToCamelCase($table_name,true)." findOneBy" . $capatalizeNonTypeString . "(" . $noncapatalizeWithTypeString . ");\n";
    } else {
    	$finder.= "\tpublic List<".snakeToCamelCase($table_name,true)."> findAllBy" . $capatalizeNonTypeString . "(" . $noncapatalizeWithTypeString . ");\n";
    }
}

//print_r($serviceImplUniqueGet);
//print_r($UniqueConatinsType);
$serviceHeader = "package ".$basePackage.".service;\n\nimport org.springframework.data.domain.Page;\nimport org.springframework.data.domain.Pageable;\n\nimport ".$basePackage.".model.".snakeToCamelCase($table_name,true).";\n\npublic interface " . snakeToCamelCase($table_name,true) . "Service {";
$serviceFuctionArray = Array();
$serviceFuctionArray[0] = "\tpublic Page<".snakeToCamelCase($table_name,true)."> getAll(Pageable pageable) throws Exception";
$serviceFuctionArray[1] = "\tpublic ".snakeToCamelCase($table_name,true)." save(".snakeToCamelCase($table_name,true)." ".snakeToCamelCase($table_name).") throws Exception";
$serviceFuction = "";
$serviceFunction.= "\tpublic ".snakeToCamelCase($table_name,true)." getBy";
$ServiceEntryflag = 0;
foreach($keyArray as $x => $x_value) {
    $subKeyArray = explode(",",$x);
	if (strpos($x, ',') === false) {
		if($x_value == "UNIQUE"){
		    if($ServiceEntryflag == 0){
		    	$ServiceEntryflag = 1;
				$serviceFunction.= "".snakeToCamelCase($x,true)."";
		    } else {
		    	$serviceFunction.= "Or".snakeToCamelCase($x,true)."";
		    }
		}
	}
}
$serviceFunction.="(String code) throws Exception";
$serviceFuctionArray[2] = $serviceFunction;
//print_r($serviceFuctionArray);
$serviceFunction = "\n";
foreach($serviceFuctionArray as $x => $x_value){
	$serviceFunction.= $x_value.";\n";
}
$serviceFooter = "\n}";
//echo $serviceFunction;


$serviceImplHeader = "package ".$basePackage.".service.impl;\n\nimport org.apache.commons.lang.StringUtils;\nimport org.apache.log4j.Logger;\nimport org.springframework.beans.factory.annotation.Autowired;\nimport org.springframework.stereotype.Service;\nimport org.springframework.data.domain.Page;\nimport org.springframework.data.domain.Pageable;\nimport ".$basePackage.".dao.".snakeToCamelCase($table_name,true)."Repository;\nimport ".$basePackage.".model.".snakeToCamelCase($table_name,true).";\nimport ".$basePackage.".service.".snakeToCamelCase($table_name,true)."Service;\nimport ".$basePackage.".util.CommonUtils;\n\n@Service\npublic class ".snakeToCamelCase($table_name,true)."ServiceImpl implements ".snakeToCamelCase($table_name,true)."Service {";

$serviceImplFunction = "\n";
foreach ($serviceFuctionArray as $key => $value) {

	$serviceImplFunction.= "\t@Override\n";
	$serviceImplFunction.= $value." {\n";
	if($key == 0) {
		$serviceImplFunction.= "\t\tif(null == pageable) {\n";
		$serviceImplFunction.= "\t\t\tlogger.warn(\"Pageable is null.\");\n";
		$serviceImplFunction.= "\t\t\tpageable = new PageRequest(0,100);\n";
		$serviceImplFunction.= "\t\t}\n";
		$serviceImplFunction.= "\t\treturn ".snakeToCamelCase($table_name)."Repository.findAll(pageable);\n";

	}
	if($key == 1) {
		$serviceImplFunction.= "\t\tif(CommonUtils.isBlank(".snakeToCamelCase($table_name).")) {\n";
		$serviceImplFunction.= "\t\t\tlogger.error(\"Input ".snakeToCamelCase($table_name)." can not be null.\");\n";
		$serviceImplFunction.= "\t\t\tthrow new Exception(\"Input ".snakeToCamelCase($table_name)." can not be null.\");\n";
		$serviceImplFunction.= "\t\t}\n";
		$serviceImplFunction.= "\t\treturn ".snakeToCamelCase($table_name)."Repository.save(".snakeToCamelCase($table_name).");\n";

	}
	if($key == 2) {
		$serviceImplFunction.= "\t\tif(StringUtils.isBlank(code)) {\n";
		$serviceImplFunction.= "\t\t\tlogger.error(\"Input code can not be null.\");\n";
		$serviceImplFunction.= "\t\t\tthrow new Exception(\"Input code can not be null.\");\n";
		$serviceImplFunction.= "\t\t}\n";
		$serviceImplFunction.= "\t\t".snakeToCamelCase($table_name,true)." ".snakeToCamelCase($table_name)." = new ".snakeToCamelCase($table_name,true)."();\n";
		if($UniqueConatinsType["Long"]) {
			$serviceImplFunction.= "\t\tif(CommonUtils.isLong(code)) {\n";
			foreach ($serviceImplUniqueGet as $key1 => $value1) {
				if($value1 == "Long") {
					$serviceImplFunction.= "\t\t\t".snakeToCamelCase($table_name)." = ".snakeToCamelCase($table_name)."Repository.".$key1."\n";
					$serviceImplFunction.= "\t\t\tif(null != ".snakeToCamelCase($table_name).") {\n";
					$serviceImplFunction.= "\t\t\t\treturn ".snakeToCamelCase($table_name).";\n";
					$serviceImplFunction.= "\t\t\t}\n";
				}
			}
			$serviceImplFunction.= "\t\t}\n";
		}
		if($UniqueConatinsType["Double"]) {
			$serviceImplFunction.= "\t\tif(CommonUtils.isDouble(code)) {\n";
			foreach ($serviceImplUniqueGet as $key1 => $value1) {
				if($value1 == "Double") {
					$serviceImplFunction.= "\t\t\t".snakeToCamelCase($table_name)." = ".snakeToCamelCase($table_name)."Repository.".$key1."\n";
					$serviceImplFunction.= "\t\t\tif(null != ".snakeToCamelCase($table_name).") {\n";
					$serviceImplFunction.= "\t\t\t\treturn ".snakeToCamelCase($table_name).";\n";
					$serviceImplFunction.= "\t\t\t}\n";
				}
			}
			$serviceImplFunction.= "\t\t}\n";
		}
		if($UniqueConatinsType["String"]) {
			$serviceImplFunction.= "\t\telse {\n";
			foreach ($serviceImplUniqueGet as $key1 => $value1) {
				if($value1 == "String") {
					$serviceImplFunction.= "\t\t\t".snakeToCamelCase($table_name)." = ".snakeToCamelCase($table_name)."Repository.".$key1."\n";
					$serviceImplFunction.= "\t\t\tif(null != ".snakeToCamelCase($table_name).") {\n";
					$serviceImplFunction.= "\t\t\t\treturn ".snakeToCamelCase($table_name).";\n";
					$serviceImplFunction.= "\t\t\t}\n";
				}
			}
			$serviceImplFunction.= "\t\t}\n";
		}
		$serviceImplFunction.= "\t\treturn ".snakeToCamelCase($table_name).";\n";
	}
	$serviceImplFunction.= "\t}\n";

}

//echo $serviceImplFunction;

$serviceImplFooter = "\n\tprivate Logger logger = Logger.getLogger(".snakeToCamelCase($table_name,true)."ServiceImpl.class.getName());\n\n\t@Autowired\n\tprivate ".snakeToCamelCase($table_name,true)."Repository ".snakeToCamelCase($table_name)."Repository;\n\n}";
//die();

//echo $model_header;
//echo $variable;
//echo $geter;
//echo $seter;
//echo $model_footer;

$fileModel = fopen($path."model/".snakeToCamelCase($table_name,true).".java", "w") or die("Unable to open file!");
$txt = $model_header;
$txt .= $variable;
$txt .= $geter;
$txt .= $seter;
$txt .= $model_footer;
fwrite($fileModel, $txt);
fclose($fileModel);


$fileRepo = fopen($path."dao/".snakeToCamelCase($table_name,true)."Repository.java", "w") or die("Unable to open file!");
$txt = $repository_header;
$txt .= $finder;
$txt .= $repository_footer;
fwrite($fileRepo, $txt);
fclose($fileRepo);


$fileService = fopen($path."service/".snakeToCamelCase($table_name,true)."Service.java", "w") or die("Unable to open file!");
$txt = $serviceHeader;
$txt .= $serviceFunction;
$txt .= $serviceFooter;
fwrite($fileService, $txt);
fclose($fileService);

$fileImplService = fopen($path."service/impl/".snakeToCamelCase($table_name,true)."ServiceImpl.java", "w") or die("Unable to open file!");
$txt = $serviceImplHeader;
$txt .= $serviceImplFunction;
$txt .= $serviceImplFooter;
fwrite($fileImplService, $txt);
fclose($fileImplService);

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

