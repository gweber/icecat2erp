<?php

//Ne te quaesiveris extra

@include "mysql.inc.php";

// path to the icecat-xml storage
$xmlstorage = "/data/icecat/";


function getDaily() {
	
	$daily_url 	= "http://data.Icecat.biz/export/freexml/daily.index.xml";
	$dailyxmlstorage 	=	$xmlstorage . "/daily/";
	$icecatuser 	= 	"user";
	$icecatpass	=	"password";
	
	// connect to icecat with gzip, each byte counts 
	$context = stream_context_create(array(
		'http' => array(
			'header'  => "Authorization: Basic " . base64_encode("$icecatuser:$icecatpass") . "\n" . 
				"Accept-Encoding: gzip\n" 
		)
	));
	// save the daily-file with date
	$today = date('Ymd');
	if ( file_exists( $dailyxmlstorage . $today .".xml") ) {	// is file already in fetch_data?
		echo "file " . $dailyxmlstorage . $today .".xml locally known\n";
		return file_get_contents($dailyxmlstorage . $today.".xml");
	} else if ( file_exists( $dailyxmlstorage . $today .".xml.gz") ){
		echo "file " . $dailyxmlstorage . $today .".xml.gz locally known\n";
		// skip 11 bytes for dont know, have read it, it works and is faster than gzopen
		return gzinflate( substr( file_get_contents($dailyxmlstorage . $today . ".xml.gz"), 11) );
	} else {	 
		$readurl = $daily_url;
		echo "read => ";
		$file_str = file_get_contents($readurl,false,$context);	// load the file over http
		if ($file_str) {	// read was successful
			file_put_contents($dailyxmlstorage . $today .".xml.gz", $file_str);
			echo strlen($file_str) ." bytes fetched and ". $dailyxmlstorage . $today .".xml.gz written\n";
			return( gzinflate( substr($file_str, 11))  );
		} else {
			die("error on fetch");
		}
	}
}// function getDaily

if ($file_str = getDaily()){
	$xml_array = json_decode(json_encode(simplexml_load_string($file_str)), TRUE);

	foreach($xml_array["files.index"]["file"] as $k => $v){
		foreach ($v as $index => $value){
			if ($index == "@attributes"){
				$product[id] = $value['Product_ID'];
				$product[supplier] = $value['Supplier_id'];
				$product[sku] = $value['Prod_ID'];
				$quarray[$value['Quality']]++;
				if ( $value['Quality'] == 'REMOVED' ) {
					$remove_array[] = $product[id];
					$delete++;
				}else {
					$reset_array[] = $product[id];
				}
			}
			
		}		
	}
	
	
	$remove_str = join(",", $remove_array); // which are to delete
	$reset_str = join(",", $reset_array);	// only reset
	$alldelete_str = join(",", array_merge($remove_array,$reset_array) );
	
	// dont delete the products, keep them with state 4 (greetings dt_deleted)
	
	$del_q = mysql_query("update product set user_id = 4 where product_id in ($remove_str) and user_id is not 4");
	echo "REMOVE: $delete products marked, ". mysql_affected_rows($del_q) . " really found\n";
	
	// it is save to delete all product features ( reset + delete)
	$del_q = mysql_query("delete from product_feature where product_id in ($alldelete_str)");
	echo "REMOVE: ". mysql_affected_rows($del_q) . " product features deleted\n";
	
	// delete the product descriptions
	$del_q = mysql_query("delete from product_description where product_id in ($alldelete_str)");
	echo "REMOVE: ". mysql_affected_rows($del_q) . " descriptions deleted\n";
	
	// delete related product if it's removed, keep them if not
	$del_q = mysql_query("delete from product_related where rel_product_id in ($remove_str)");
	echo "REMOVE: ". mysql_affected_rows($del_q) . " to-relations  deleted\n";
	
	// delete all relations from the products, removed or reset; on reset, it will be read and parsed afterwards
	mysql_query("delete from product_related where product_id in ($alldelete_str)");
	echo "REMOVE: ". mysql_affected_rows($del_q) . " from-relations deleted\n";
	
	// delete the xml(.gz) if it exists 
	foreach($reset_array as $tempid => $tempvalue){
		if ( file_exists( $xmlstorage . $tempvalue .".xml") ) {	// is file already in fetch_data?
			unlink($xmlstorage . $tempvalue .".xml");
			$files_deleted++;
		} else if ( file_exists( $xmlstorage . $tempvalue .".xml.gz") ){
			unlink($xmlstorage . $tempvalue .".xml.gz");
			$files_deleted++;
		} else {	
			// error: fnf
		}
	}
	echo "DELETE: $files_deleted were removed\n";
	
	// set the status to 5 (high prio for fetch)
	$del_q = mysql_query("update product set user_id = 5 where product_id in ($reset_str)");
	echo "UPDATE: ". mysql_affected_rows($del_q) . " products marked for fetch\n";
} else {
	echo "error on file_str\n";
}

?>
