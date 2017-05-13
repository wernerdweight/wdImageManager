<?php

namespace WernerDweight\ImageManager\Manager;

use WernerDweight\ImageManager\Image\Image;

Class ImageManager {

	private $image;
	private $secret;
	private $autorotate;

	public function __construct(string $secret = null, bool $autorotate = false) {
		if($secret) {
			$this->secret = $secret;
		}
		$this->autorotate = $autorotate;
	}

	public function loadImage(string $path) : self {
		try {
			$this->image = new Image($path, null, $this->secret, $this->autorotate);
		} catch (\Exception $e) {
			throw $e;
		}
		return $this;
	}

	public function saveImage(string $path, string $name, string $ext = null, int $quality = 100) : self {
		try {
			$this->image->save($path, $name, $ext, $quality);
		} catch (\Exception $e) {
			throw $e;
		}
		return $this;
	}

	public function resizeImage(Image &$image, int $width, int $height, bool $crop = false) : self {
		if($image->getEncrypted()) {
			$this->decryptImage($image);
			$encrypt = true;
		}
		else {
			$encrypt = false;
		}

		$dimensions = $this->getAdjustedImageDimensions($image, $width, $height, $crop);
		$tmp = new Image(null, $image->getExt(), $this->secret, $this->autorotate);
		$tmp->create($dimensions);
		imagecopyresampled($tmp->getData(), $image->getData(), 0, 0, 0, 0, $dimensions['width'], $dimensions['height'], $image->getWidth(), $image->getHeight());
		$image->destroy();
		$image = $tmp;
		if($crop) {
			$this->cropImage($image, $width, $height);
		}
		
		if($encrypt) {
			$this->encryptImage($image);
		}
		return $this;
	}

	public function resize(int $width, int $height, bool $crop = false) : self {
		return $this->resizeImage($this->image, $width, $height, $crop);
	}

	public function cropImage(Image &$image, int $width, int $height) : self {
		if($image->getEncrypted()) {
			$this->decryptImage($image);
			$encrypt = true;
		}
		else {
			$encrypt = false;
		}

		$crop = $this->getAdjustedImageCropDimensions($image, $width, $height);
		$centerX = ($image->getWidth() / 2) - ($crop['width'] / 2);
		$centerY = ($image->getHeight() / 2) - ($crop['height'] / 2);
		$tmp = new Image(null, $image->getExt(), $this->secret, $this->autorotate);
		$tmp->create([
			'width' => $width,
			'height' => $height
		]);
		imagecopyresampled($tmp->getData(), $image->getData(), 0, 0, $centerX, $centerY, $width, $height, $crop['width'], $crop['height']);
		$image->destroy();
		$image = $tmp;

		if($encrypt) {
			$this->encryptImage($image);
		}
		return $this;
	}

	public function crop(int $width, int $height) : self {
		return $this->cropImage($this->image, $width, $height);
	}

	public function encryptImage(Image &$image) : self {
		$image->encrypt();
		return $this;
	}

	public function encrypt() : self {
		return $this->encryptImage($this->image);
	}

	public function decryptImage(Image &$image) : self {
		$image->decrypt();
		return $this;
	}

	public function decrypt() : self {
		return $this->decryptImage($this->image);
	}

	private function getAdjustedImageCropDimensions(Image &$image, int $width, int $height, bool $relative = false) : array {
		$w = $image->getWidth() / $width;
		$h = $image->getHeight() / $height;

		if($w < 1 || $h < 1) {
			if($w < $h) {
				return [
					'width' => $width * $w,
					'height' => $height * $w
				];
			}
			else {
				return [
					'width' => $width * $h,
					'height' => $height * $h
				];
			}
		}
		else {
			if($w < $h) {
				return [
					'width' => $width * (true === $relative ? $w : 1),
					'height' => $height * (true === $relative ? $w : 1)
				];
			}
			else {
				return [
					'width' => $width * (true === $relative ? $h : 1),
					'height' => $height * (true === $relative ? $h : 1)
				];
			}
		}
	}

	private function getAdjustedImageDimensions(Image &$image, int $width, int $height, bool $crop = false) : array {
		if($image->getWidth() / $image->getHeight() > $width / $height) {		/// current is wider than new
			if($crop) {	/// upscale prevention
				return [
					'width' => $this->getImageWidth($image, $height),
					'height' => $height
				];
			}
			else {
				return [
					'width' => $width,
					'height' => $this->getImageHeight($image, $width)
				];
			}
		}
		else if($image->getWidth() / $image->getHeight() < $width / $height) {	/// current is taller than new
			if($crop) {	/// upscale prevention
				return [
					'width' => $width,
					'height' => $this->getImageHeight($image, $width)
				];
			}
			else {
				return [
					'width' => $this->getImageWidth($image, $height),
					'height' => $height
				];
			}
		}
		else{									/// current has same aspect ratio as new
			return [
				'width' => $width,
				'height' => $height
			];
		}
	}

	public function addImageWatermark(Image &$image, array $parameters) : self {
		$watermark = new Image($parameters['file'], null, $this->secret, $this->autorotate);

		/// temporarily enable alphablending to be able to use 32-bit images
		imagealphablending($watermark->getData(), true);
		imagealphablending($image->getData(), true);

		/// determine watermark position from config
		if(true === isset($parameters['position'])) {
			$top = intval($parameters['position']['top']) / 100;
			$left = intval($parameters['position']['left']) / 100;
		}
		else {
			$top = $left = 1;
		}

		/// determine watermark size from config
		if(true === isset($parameters['size'])) {
			if($parameters['size'] === 'cover') {
				/// cover dimensions are the same as crop dimensions
				$dimensions = $this->getAdjustedImageCropDimensions($watermark, $image->getWidth(), $image->getHeight(), true);
				imagecopyresampled($image->getData(), $watermark->getData(), 0, 0, ($watermark->getWidth() - $dimensions['width']) * $left, ($watermark->getHeight() - $dimensions['height']) * $top, $image->getWidth(), $image->getHeight(), $dimensions['width'], $dimensions['height']);
			}
			else if($parameters['size'] === 'contain') {
				$dimensions = $this->getAdjustedImageDimensions($watermark, $image->getWidth(), $image->getHeight());
				imagecopyresampled($image->getData(), $watermark->getData(), ($image->getWidth() - $dimensions['width']) * $left, ($image->getHeight() - $dimensions['height']) * $top, 0, 0, $dimensions['width'], $dimensions['height'], $watermark->getWidth(), $watermark->getHeight());
			}
			else {		/// percentage
				$dimensions = $this->getAdjustedImageDimensions($watermark, $image->getWidth(), $image->getHeight());
				$dimensions['width'] *= (intval($parameters['size']) / 100);
				$dimensions['height'] *= (intval($parameters['size']) / 100);
				imagecopyresampled($image->getData(), $watermark->getData(), ($image->getWidth() - $dimensions['width']) * $left, ($image->getHeight() - $dimensions['height']) * $top, 0, 0, $dimensions['width'], $dimensions['height'], $watermark->getWidth(), $watermark->getHeight());
			}
		}
		else {
			imagecopy($image->getData(), $watermark->getData(), ($image->getWidth() - $watermark->getWidth()) * $left, ($image->getHeight() - $watermark->getHeight()) * $top, 0, 0, $watermark->getWidth(), $watermark->getHeight());
		}

		/// disable alphablending again to be able to save transparency
		imagealphablending($watermark->getData(), false);
		imagealphablending($image->getData(), false);

		return $this;
	}

	public function addWatermark(array $parameters) : self {
		return $this->addImageWatermark($this->image, $parameters);
	}

	private function getImageWidth(Image &$image, int $height) : int {
		return intval($height * ($image->getWidth() / $image->getHeight()));
	}

	private function getImageHeight(Image &$image, int $width) : int {
		return intval($width * ($this->image->getHeight() / $this->image->getWidth()));
	}

}
