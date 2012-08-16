<?php

abstract class _Image {

	private $format;
	private $filename;
	private $curr_width;
	private $curr_height;

	private $max_width;
	private $max_height;
	
	private $quality = 80;
	
	private $im;

	public function __construct() {
		
		//确保GD库已经安装
		if(!function_exists("gd_info")) {
			throw new Error_Exception('You do not have the GD Library installed.');
		}
		
	}
   
	/**
	 * Class destructor
	 *
	 */
	public function __destruct() {
		if(is_resource($this->im)) @ImageDestroy($this->im);
	}

	/**
	 * Returns the information of the image
	 *	current_width, current_height
	 * @return int
	 */
	function __get($name) {
		switch($name){
		case 'current_width':
			return $this->curr_width;
		case 'current_height':
			return $this->curr_height;
		}
		return NULL;
	}

	private function & calc_size($width, $height, $constraint=TRUE) {
		
		$rc = ( $width/$height) > ($this->curr_width/$this->curr_height );
		
		if($rc xor $constraint){
			$w = $width;
			$h = $w * $this->curr_height / $this->curr_width;
		} else {
			$h = $height;
			$w = $h * $this->curr_width / $this->curr_height;
		}

		return array(round($w), round($h));
	}

	public function resize($max_width = 0, $max_height = 0, $constraint=TRUE) {

		list($width, $height)=$this->calc_size($max_width, $max_height, $constraint);
		
		if(function_exists('ImageCreateTrueColor')) {
			$im = ImageCreateTrueColor($width, $height);
			ImageAlphaBlending($im, false);
			$color = $this->background_color ?: ImageColorAllocateAlpha($this->im, 0, 0, 0, 127);
			ImageFill($im,0,0,$color);
			ImageAlphaBlending($im, true);
			ImageSaveAlpha($im, true);
		} 
		else {
			$im = ImageCreate($width, $height);
		}
		
		@ImageCopyResampled(
			$im,
			$this->im,
			0,
			0,
			0,
			0,
			$width,
			$height,
			$this->curr_width,
			$this->curr_height
		);

		$this->im = $im;
		$this->curr_width = $width;
		$this->curr_height = $height;
	}

	public function crop($left,$top,$width,$height) {

		//make sure the cropped area is not greater than the size of the image
		// $width = min($width, $this->curr_width);
	   	// $height = min($height, $this->curr_height);

		//make sure not starting outside the image
		// $left = max(0, min($this->curr_width - $width, $left));
		// $top = max(0, min($this->curr_height - $height, $top));

		if ($left < 0) {$dleft = - $left; $left = 0; }
		if ($top < 0) {$dtop = - $top; $top = 0; }

		if(function_exists("ImageCreateTrueColor")) {
			$im = ImageCreateTrueColor($width,$height);
			ImageAlphaBlending($im, false);
			ImageSaveAlpha($im, true);
			$color = $this->background_color ?: ImageColorAllocateAlpha($this->im, 0, 0, 0, 127);
			ImageFill($im, 0, 0, $color);
			ImageAlphaBlending($im, true);
		}
		else {
			$im = ImageCreate($width,$height);
		}

		ImageCopy(
			$im,
			$this->im,
			$dleft,
			$dtop,
			$left,
			$top,
			$this->curr_width - $left,
			$this->curr_height - $top
		);

		$this->im = $im;
		$this->curr_width = $width;
		$this->curr_height = $height;
	}

	public function crop_center($width, $height) {
		// $width = min($width, $this->curr_width);
	   	// $height = min($height, $this->curr_height);
		
		$left = round(($this->curr_width - $width) / 2);
		$top = round(($this->curr_height - $height) / 2);
		$this->crop($left, $top, $width, $height);
	}

	public function quality($quality=100){
		$this->quality=$quality;
		return $this;
	}

	public function show($format=NULL, $filename=NULL) {
	
		if ($this->background_color !== NULL) {
			$im = ImageCreateTrueColor($this->curr_width,$this->curr_height);
			ImageAlphaBlending($im,false);
			ImageFill($im, 0, 0, $this->background_color);
			ImageAlphaBlending($im,true);
			ImageCopy($im, $this->im, 0, 0, 0, 0, $this->curr_width, $this->curr_height);
		}
		else {
			$im = $this->im;
		}
	
		if(!$format)$format=$this->format;
		switch($format) {
		case 'gif':
			if($filename) {
				ImageGif($im,$filename);
			}
			else {
				header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
				header('Pragma: public');
				header('Cache-Control: max-age=604800');
				header('Content-type: image/gif');
				ImageGif($im);
				exit();
			}
			break;
		case 'jpg':
			if($filename) {
				ImageJpeg($im, $filename, $this->quality);
			}
			else {
				header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
				header('Pragma: public');
				header('Cache-Control: max-age=604800');
				header('Content-type: image/jpeg');
				ImageJpeg($im,'',$this->quality);
				exit();
			}
			break;
		case 'png':
			if($filename) {
				ImagePng($im, $filename);
			}
			else {
				header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
				header('Pragma: public');
				header('Cache-Control: max-age=604800');
				header('Content-type: image/png');
				ImagePng($im);
				exit();
			}
			break;
		}
	}

	public function save($format=NULL, $filename) {
		$this->show($format, $filename);
	}

	public function make_reflection($percent=50, $opacity=100, $border = NULL, $padding=2) {
		$width = $this->curr_width;
		$height = $this->curr_height;

		$reflectionHeight = (int) $height * ($percent / 100);
		$newHeight = $height + $reflectionHeight + $padding;
 
		$im = ImageCreateTrueColor($width,$newHeight);

		ImageAlphaBlending($im,false);
	   	ImageSaveAlpha($im,true);

		ImageCopy($im, $this->im, 0, 0, 0, 0, $width, $height);

		if($border) {
			if(!is_array($border))$border=array('width'=>$border);
			if(!isset($border['color']))$border['color']='#ffffff';
			if(!isset($border['style']))$border['style']='solid';
			$rgb = $this->hex2rgb($border['color'],false);
			$colorToPaint = imagecolorallocate($im,$rgb[0],$rgb[1],$rgb[2]);
			imagesetthickness($im, $border['width']);
			
			switch($border['style']){
			default:
				imagesetstyle($im, NULL);
				$hbw=$border['width']/2;
				$cleft=$ctop=$hbw;
				$cright=$width-$hbw;
				$cbottom=$height-$hbw;
				
				imageline($im,0,$ctop,$width,$ctop,$colorToPaint); //top line
				imageline($im,0,$cbottom,$width,$cbottom,$colorToPaint); //bottom line
				imageline($im,$cleft,0,$cleft,$height,$colorToPaint); //left line
				imageline($im,$cright,0,$cright,$height,$colorToPaint); //right line
			}
			
		}

		$colorToPaint = ImageColorAllocateAlpha($im,0,0,0,127);
		ImageFilledRectangle($im,0,$height,$width,$newHeight,$colorToPaint);
		$opacity=$opacity/100; 
		for($i=0;$i<$reflectionHeight;$i++) {
			$alpha = (1 - (1 - $i/$reflectionHeight)*$opacity)*127;
		   	for($j=0;$j<$width;$j++) {
		   		$rgb = imagecolorat($im, $j, $height-$i-1);
			 	$colorToPaint = imagecolorallocatealpha($im
			 		, (($rgb>>16) & 255), (($rgb>>8) & 255), ($rgb & 255)
			 		, $alpha);
			 	imagesetpixel($im, $j, $height+$i+$padding, $colorToPaint);
		   	}
 
		}

		$this->im = $im;
		$this->curr_width = $width;
		$this->curr_height = $newHeight;
	}

	private $background_color;
	function background_color($color = NULL, $alpha = 0) {

		if ($color !== NULL) {
		
			if ($color === 'transparent') {
				$this->background_color = ImageColorAllocateAlpha($this->im, 0, 0, 0, 127);
			}
			else {
				$rgb = $this->hex2rgb($color);
				$this->background_color = ImageColorAllocateAlpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
			}
		
			return $this;
		}
		
		return $this->background_color;
	}

	private $text_color;
	function text_color($color = NULL, $alpha = 0) {

		if ($color !== NULL) {
		
			if ($color === 'transparent') {
				$this->text_color = ImageColorAllocateAlpha($this->im, 0, 0, 0, 127);
			}
			else {
				$rgb = $this->hex2rgb($color);
				$this->text_color = ImageColorAllocateAlpha($this->im, $rgb[0], $rgb[1], $rgb[2], $alpha);
			}
		
			return $this;
		}
		
		return $this->text_color;
	}

	private function hex2rgb($hex, $asString = false) {
	
		// strip off any leading #
		if (0 === strpos($hex, '#')) {
		   $hex = substr($hex, 1);
		} elseif (0 === strpos($hex, '&H')) {
		   $hex = substr($hex, 2);
		}

		// break into hex 3-tuple
		$cutpoint = ceil(strlen($hex) / 2)-1;
		$rgb = explode(':', wordwrap($hex, $cutpoint, ':', $cutpoint), 3);

		// convert each tuple to decimal
		$rgb[0] = (isset($rgb[0]) ? hexdec($rgb[0]) : 0);
		$rgb[1] = (isset($rgb[1]) ? hexdec($rgb[1]) : 0);
		$rgb[2] = (isset($rgb[2]) ? hexdec($rgb[2]) : 0);

		return ($asString ? "{$rgb[0]} {$rgb[1]} {$rgb[2]}" : $rgb);
	}
	
	public function rotate($direction = 'CW') {
		if($direction == 'CW') {
			$im = imagerotate($im,-90,0);
		}
		else {
			$im = imagerotate($im,90,0);
		}

		$this->im = $im;
		$this->curr_width = $this->curr_height;
		$this->curr_height = $this->curr_width;
	}
 
	public function text($text, $x=0, $y=0, $font='', $font_size=18){

		$box = ImageTTFBbox($font_size, 0, $font, $text);
		$text_width = abs($box[4] - $box[0]);
		$text_height = abs($box[5] - $box[1]);
		
		$wm = @ImageCreateTrueColor($text_width, $text_height);
		
		ImageAlphaBlending($wm, FALSE);
		
		if ($this->background_color) {
			$bgcolor  = $this->background_color;
		}
		else {
			$bgcolor = ImageColorAllocateAlpha($wm, 0, 0, 0, 127);
		}

		ImageFilledRectangle($wm, 0, 0, $text_width, $text_height, $bgcolor);
				
		ImageAlphaBlending($wm, TRUE);
		
		if ($this->text_color) {
			$color = $this->text_color;
		}
		else {
			$color = ImageColorAllocateAlpha($wm, 0, 0, 0, 0);
		}

		ImageTTFText($wm,$font_size,0,0,$text_height - 2, $color, $font, $text);
		
		ImageAlphaBlending($this->im, TRUE);
		ImageCopy($this->im, $wm, $x, $y, 0, 0, $text_width, $text_height);
		
	}

	static function load($filename, $format=NULL) {
		$image = new Image;
		
		//检查文件是否存在
		if(!file_exists($filename)) {
			throw new Error_Exception(T('文件%filename无法找到.', array('%filename'=>$filename)));
		}
		//检查文件是否可读
		elseif(!is_readable($filename)) {
			throw new Error_Exception(T('文件%filename无法读取.', array('%filename'=>$filename)));
		}
		
		if (!$format) $format = File::extension($filename);
		
		switch(strtolower($format)) {
		case 'gif':
			$image->format = 'gif'; break;
		case 'jpg': 
		case 'jpeg':
			$image->format = 'jpg'; break;
		case 'png':
			$image->format = 'png'; break;
		default:
			throw new Error_Exception(T('文件%filename不是可识别的图片格式(GIF/JPG/PNG)', array('%filename'=>$filename)));
		}

		$image->filename=$filename;

		//init resources if no errors
		switch($image->format) {
			case 'gif':
				$image->im = @ImageCreateFromGif($image->filename);
				break;
			case 'jpg':
				$image->im = @ImageCreateFromJpeg($image->filename);
				break;
			case 'png':
				$image->im = @ImageCreateFromPng($image->filename);
				break;
		}
		
		if (!$image->im) {
			throw new Error_Exception(T('文件%filename不是可识别的图片格式(GIF/JPG/PNG)', array('%filename'=>$filename)));
		}
		
		$size = GetImageSize($image->filename);
		list($image->curr_width, $image->curr_height) = $size;
		
		if ($image->format == 'png') {
			$im = ImageCreateTrueColor($size[0], $size[1]);
			ImageAlphaBlending($im,false);
			ImageSaveAlpha($im,true);
			ImageCopy($im, $image->im, 0, 0, 0, 0, $size[0], $size[1]);
			$image->im = $im;
		}
		
		return $image;
	}

	static function load_from_data($data) {
		$image = new Image;
		
		$image->im = @imagecreatefromstring($data);
		if (!$image->im) {
			throw new Error_Exception(T('不可识别的图片数据'));
		}
		
		$image->format = 'png';
		
		$image->curr_width = ImageSX($image->im);
		$image->curr_height = ImageSY($image->im);

		return $image;
	}
	
	function & data() {
		ob_clean();
		ob_start();
		ImagePng($this->im);
		$output=ob_get_contents();
		ob_end_clean();
		return $output;
	}
	
	static function show_file($filename, $format = 'png') {
	
		header('Expires: Thu, 15 Apr 2100 20:00:00 GMT'); 
		header('Pragma: public');
		header('Cache-Control: max-age=604800');

		$format = strtolower($format);

		switch($format) {
		case 'gif':
			header('Content-type: image/gif');
			break;
		case 'jpg':
			header('Content-type: image/jpeg');
			break;
		case 'png':
			header('Content-type: image/png');
			break;
		}

		@readfile($filename);
		exit;

	}

}
