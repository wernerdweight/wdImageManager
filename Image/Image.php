<?php

namespace WernerDweight\ImageManager\Image;

Class Image {

	private $width;
	private $height;
	private $ext;
	private $workingData;
	private $secret;
	private $encrypted;
	private $autorotate;

	public function __construct(string $path = null, string $ext = null, string $secret = null, bool $autorotate = false) {
		$this->secret = substr(hash('sha256', ($secret ? $secret : 'I did not want to tell you, but this is not secret at all (change this in config)!')), 0, 32);
		$this->autorotate = $autorotate;
		if($path) {
			$this->load($path);
		}
		if($ext) {
			$this->ext = $ext;
		}
	}

	public function create(array $dimensions) : self {
		$this->width = $dimensions['width'];
		$this->height = $dimensions['height'];
		$this->workingData = imagecreatetruecolor($this->width, $this->height);
		if($this->ext == 'png') {
			$this->setTransparency(false, true);
		}
		if($this->ext == 'gif') {
			$this->setTransparency();
		}
		$this->encrypted = false;
		return $this;
	}

	private function autoRotate(string $path) : self {
		$exifData = exif_read_data($path);
		if(true === isset($exifData['Orientation'])) {
			switch ($exifData['Orientation']) {
				case 3:
					$this->workingData = imagerotate($this->workingData, 180, 0);
					break;
				case 6:
					$this->workingData = imagerotate($this->workingData, -90, 0);
					break;
				case 8:
					$this->workingData = imagerotate($this->workingData, 90, 0);
					break;
			}
		}
		return $this;
	}

	private function imagecreatefromjpeg(string $path) : self {
		$this->workingData = imagecreatefromjpeg($path);
		$this->encrypted = false;
		if(true === $this->autorotate) {
			$this->autoRotate($path);
		}
		return $this;
	}

	private function imagecreatefrompng(string $path) : self {
		$this->workingData = imagecreatefrompng($path);
		$this->setTransparency(false, true);
		$this->encrypted = false;
		return $this;
	}

	private function imagecreatefromgif(string $path) : self {
		$this->workingData = imagecreatefromgif($path);
		$this->setTransparency();
		$this->encrypted = false;
		return $this;
	}

	private function imagecreatefromwdImage(string $path) : self {
		$this->workingData = file_get_contents($path);
		$this->encrypted = true;
		return $this;
	}

	public function load(string $path) : self {
		$this->ext = strtolower(substr(strrchr($path, '.'), 1));

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

		if(!$this->encrypted) {
			$this->getDimensions();
		}
		return $this;
	}

	private function getDimensions() : self {
		$this->width = imagesx($this->workingData);
		$this->height = imagesy($this->workingData);
		return $this;
	}

	public function save(string $path, string $name, string $ext = null, int $quality = 100) : bool {
		if(!is_dir($path)) {
			mkdir($path, 0777, true);
		}
		if($ext === null) {
			$ext = $this->ext;
		}
		if($this->encrypted) {
			file_put_contents($path.$name.'.wdImage', $this->workingData);
			return true;
		}
		else if(imagetypes()) {
			switch ($ext) {
				case 'gif':
					if(IMG_GIF) {
						imagegif($this->workingData, $path.$name.'.'.$ext);
					}
					break;
				case 'jpeg':
				case 'jpg':
					if(IMG_JPG) {
						imagejpeg($this->workingData, $path.$name.'.'.$ext, $quality);
					}
					break;
				case 'png':
					if(IMG_PNG) {
						imagepng($this->workingData, $path.$name.'.'.$ext, round(9 - ((9*$quality) / 100)));
					}
					break;
				default:
					throw new \Exception("Unsupported file type", 1);
			}
			return false;
		}
	}

	private function getType(string $ext) : string {
		switch ($ext) {
			case 'jpg':
			case 'jpeg':
				return 'jpeg';
			case 'png':
				return 'png';
			case 'gif':
				return 'gif';
			case 'wdimage':
				return 'wdImage';
			default:
				throw new \Exception("Unsupported file type", 1);
		}
	}

	public function encrypt() : self {
		if($this->encrypted) {
			throw new \Exception("Can't encrypt encrypted image", 1);
		}

		/// use buffer to get image content
		ob_start();
		imagejpeg($this->workingData, NULL, 100);
		$this->workingData =  ob_get_contents();
		ob_end_clean();

		$this->workingData = rtrim(
			mcrypt_encrypt(
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
			),
			"\0"
		);

		$this->encrypted = true;

		return $this;
	}

	public function decrypt() : self {
		if(!$this->encrypted) {
			throw new \Exception("Can't decrypt unencrypted image", 1);
		}

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
			),
			"\0"
		);
		$this->encrypted = false;

		$this->workingData = imagecreatefromstring($this->workingData);

		if(!$this->width || !$this->height) {
			$this->getDimensions();
		}

		return $this;
	}

	public function getEncrypted() : bool {
		return $this->encrypted;
	}

	public function destroy() : self {
		imagedestroy($this->workingData);
		return $this;
	}

	public function getData() {
		return $this->workingData;
	}

	public function setData($data) : self {
		$this->workingData = $data;
		return $this;
	}

	public function getWidth() : int {
		return $this->width;
	}

	public function getHeight() : int {
		return $this->height;
	}

	public function getExt() : string {
		return $this->ext;
	}

	private function setTransparency($alphaBlending = null, $saveAlpha = null) : self {
		imagecolortransparent($this->workingData, imagecolorallocate($this->workingData, 0, 0, 0));
		if($alphaBlending !== null) {
			imagealphablending($this->workingData, $alphaBlending);
		}
		if($saveAlpha !== null) {
			imagesavealpha($this->workingData, $saveAlpha);
		}
		return $this;
	}

}
