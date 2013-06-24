<?php

namespace Model {

	class cURL {
	
		static function download($url, $file) {
		  	$fh = fopen($file, 'wb'); 
			if($fh){
				$ch = curl_init();
				if($ch) {
					curl_setopt($ch, CURLOPT_URL, $url);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_FILE, $fh);
					curl_exec($ch);
					curl_close($ch);
				}
				fclose($fh);
			}
		}

	}
	
}

