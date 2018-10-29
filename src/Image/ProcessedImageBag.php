<?php

namespace WernerDweight\ImageManager\Image;

Class ProcessedImageBag {

	protected $originalWidth = 0;
	protected $originalHeight = 0;
	protected $originalFileSize = 0;
	protected $originalName = null;
	protected $assetPath = null;
	protected $exifData = null;
	protected $autorotate = false;

	public function __construct(string $assetPath, string $originalName, bool $autorotate = false) {
		$this->assetPath = $assetPath;
		$this->originalName = $originalName;
		$this->autorotate = $autorotate;
		$this->loadOriginalFileSize();
		$this->loadDimensions();
		$this->loadExifData();
	}

	protected function loadExifData() : self {
		/// only available for jpeg images
		if(preg_match('/\.jp[e]?g$/i', $this->assetPath)) {
			$this->exifData = exif_read_data($this->assetPath);
			/// fix rotation if autorotate is true
			if(true === $this->autorotate && true === isset($this->exifData['Orientation'])) {
				switch ($this->exifData['Orientation']) {
					case 6:	/// -90 degrees
					case 8:	/// 90 degrees
						$tmpWidth = $this->originalWidth;
						$this->originalWidth = $this->originalHeight;
						$this->originalHeight = $tmpWidth;
						break;
				}
			}
		}
		return $this;
	}

	protected function loadDimensions() : self {
		$imagesize = getimagesize($this->assetPath);
		$this->originalWidth = $imagesize[0];
		$this->originalHeight = $imagesize[1];
		return $this;
	}

	protected function loadOriginalFileSize() : self {
		$this->originalFileSize = filesize($this->assetPath);
		return $this;
	}

	public function getOriginalWidth() : int {
		return $this->originalWidth;
	}
	
	public function getOriginalHeight() : int {
		return $this->originalHeight;
	}
	
	public function getOriginalFileSize() : int {
		return $this->originalFileSize;
	}
	
	public function getOriginalName() : string {
		return $this->originalName;
	}
	
	public function getAssetPath() : string {
		return $this->assetPath;
	}
	
	public function getExifData() : ?array {
		return $this->exifData;
	}
	

}
