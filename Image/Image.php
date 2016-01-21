<?php

namespace WernerDweight\ImageManager\Image;

Class Image{
	private $width;
	private $height;
	private $ext;
	private $workingData;
	private $secret;
	private $encrypted;

	public function __construct($path = null,$ext = null,$secret = null){
		$this->secret = hash('sha256',($secret ? $secret : 'I did not want to tell you, but this is not secret at all (change this in config)!'));
		if($path) $this->load($path);
		if($ext) $this->ext = $ext;
	}

	public function create($dimensions){
		$this->width = $dimensions['width'];
		$this->height = $dimensions['height'];
		$this->workingData = imagecreatetruecolor($this->width,$this->height);
		if($this->ext == 'png') $this->setTransparency(false,true);
		if($this->ext == 'gif') $this->setTransparency();
		$this->encrypted = false;
	}

	private function imagecreatefromjpeg($path){
		$this->workingData = imagecreatefromjpeg($path);
		$this->encrypted = false;
	}

	private function imagecreatefrompng($path){
		$this->workingData = imagecreatefrompng($path);
		$this->setTransparency(false,true);
		$this->encrypted = false;
	}

	private function imagecreatefromgif($path){
		$this->workingData = imagecreatefromgif($path);
		$this->setTransparency();
		$this->encrypted = false;
	}

	private function imagecreatefromwdImage($path){
		$this->workingData = file_get_contents($path);
		$this->encrypted = true;
	}

	public function load($path){
		$this->ext = strtolower(substr(strrchr($path,'.'),1));

		try {
			switch ($this->getType($this->ext)) {
				case 'jpeg':
					$this->imagecreatefromjpeg($path);
					break;
				case 'png':
					$this->imagecreatefrompng($path);
					break;
				case 'gif':
					$this->imagecreatefromgif($path);
					break;
				case 'wdImage':
					$this->imagecreatefromwdImage($path);
					break;
				default:
					throw new \Exception("This image format is not supported!", 1);
			}
		} catch (\Exception $e) {
			throw $e;
		}

		if(!$this->encrypted) $this->getDimensions();
	}

	private function getDimensions(){
		$this->width = imagesx($this->workingData);
		$this->height = imagesy($this->workingData);
	}

	public function save($path,$name,$ext,$quality){
		if(!is_dir($path)) {
			mkdir($path,0777,true);
		}
		if($ext === null) $ext = $this->ext;
		if($this->encrypted){
			file_put_contents($path.$name.'.wdImage',$this->workingData);
			return true;
		}
		else if(imagetypes()){
			switch ($ext) {
				case 'gif':
					if(IMG_GIF) imagegif($this->workingData,$path.$name.'.'.$ext);
					break;
				case 'jpeg':
				case 'jpg':
					if(IMG_JPG) imagejpeg($this->workingData,$path.$name.'.'.$ext,$quality);
					break;
				case 'png':
					if(IMG_PNG) imagepng($this->workingData,$path.$name.'.'.$ext,round(9 - ((9*$quality)/100)));
					break;
				default:
					throw new \Exception("Unsupported file type", 1);
			}
		}
	}

	private function getType($ext){
		switch ($ext) {
			case 'jpg':
			case 'jpeg':
				return 'jpeg';
				break;
			case 'png':
				return 'png';
				break;
			case 'gif':
				return 'gif';
				break;
			case 'wdimage':
				return 'wdImage';
				break;
			default:
				throw new \Exception("Unsupported file type", 1);
				break;
		}
	}

	public function encrypt(){
		if($this->encrypted) throw new \Exception("Can't encrypt encrypted image", 1);

		/// use buffer to get image content
		ob_start();
		imagejpeg($this->workingData,NULL,100);
		$this->workingData =  ob_get_contents();
		ob_end_clean();

		$this->workingData = rtrim(
				mcrypt_encrypt(
					MCRYPT_RIJNDAEL_128,
					$this->secret, $this->workingData,
					MCRYPT_MODE_ECB,
					mcrypt_create_iv(
						mcrypt_get_iv_size(
							MCRYPT_RIJNDAEL_128,
							MCRYPT_MODE_ECB
						),
						MCRYPT_RAND
					)
			), "\0"
		);

		$this->encrypted = true;
	}

	public function decrypt(){
		if(!$this->encrypted) throw new \Exception("Can't decrypt unencrypted image", 1);

		$this->workingData = rtrim(
			mcrypt_decrypt(
				MCRYPT_RIJNDAEL_128,
				$this->secret,
				$this->workingData,
				MCRYPT_MODE_ECB,
				mcrypt_create_iv(
					mcrypt_get_iv_size(
						MCRYPT_RIJNDAEL_128,
						MCRYPT_MODE_ECB
					),
					MCRYPT_RAND
				)
			), "\0"
		);
		$this->encrypted = false;

		$this->workingData = imagecreatefromstring($this->workingData);

		if(!$this->width || !$this->height) $this->getDimensions();
	}

	public function getEncrypted(){
		return $this->encrypted;
	}

	public function destroy(){
		imagedestroy($this->workingData);
	}

	public function getData(){
		return $this->workingData;
	}

	public function setData($data){
		$this->workingData = $data;
	}

	public function getWidth(){
		return $this->width;
	}

	public function getHeight(){
		return $this->height;
	}

	public function getExt(){
		return $this->ext;
	}

	private function setTransparency($alphaBlending = null,$saveAlpha = null){
		imagecolortransparent($this->workingData, imagecolorallocate($this->workingData, 0, 0, 0));
		if($alphaBlending !== null) imagealphablending($this->workingData, $alphaBlending);
		if($saveAlpha !== null) imagesavealpha($this->workingData, $saveAlpha);
	}

}
