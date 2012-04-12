<?php
$iceurl = "http://data.Icecat.biz/export/freexml/DE/";  // url to fetch xml-file

$xmlstorage = "/data/icecat/";		// where are my xml-files

$context = stream_context_create(array(
	'http' => array(
		'header'  => "Authorization: Basic " . base64_encode("username:password") . 	"\nAccept-Encoding: gzip\n" 
	)
));

@include "mysql.inc.php";

// better with status request and sleep, will come
while (1){
	$result = mysql_query("select product_id, user_id from product where user_id in (5,1)  order by user_id desc, updated desc limit 0,100");
	echo "next bucket\n";
	while ($db_array = mysql_fetch_array($result)){
		$id = $db_array['product_id'];
		echo "$id => ";
		if ( $id ){
			// if file exists, read it, else just get it, update user_id and get next
			if ( file_exists( $xmlstorage . $id .".xml") ) {	// is file already in fetch_data?
				echo "file " . $xmlstorage . $id .".xml locally known\n";
				$updq = mysql_query("update product set user_id = 10 where product_id='$id' limit 1");
			} else if ( file_exists( $xmlstorage . $id .".xml.gz") ){
				echo "file " . $xmlstorage . $id .".xml.gz locally known\n";
				$updq = mysql_query("update product set user_id = 10 where product_id='$id' limit 1");
			} else {	
				$readurl = $iceurl . $id .".xml";
				echo "[" . $db_array[user_id] . "]";
				echo "read => ";
				$file_str = file_get_contents($readurl,false,$context);	// load the file over http
	
				if ($file_str) {	// read was successful
					file_put_contents($xmlstorage . $id .".xml.gz", $file_str);
					echo strlen($file_str) ." bytes fetched and gz written\n";
					$updq = mysql_query("update product set user_id = 10 where product_id='$id' limit 1");
				} else {
					echo "no result from fetch\n";
					$updq = mysql_query("update product set user_id = 2 where product_id='$id' limit 1");
				}
			}
		} // if id
	} // while db_array
} // while 1
?>
