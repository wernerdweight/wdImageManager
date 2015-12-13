<?php

namespace WernerDweight\ImageManager\Image;

Class ProcessedImageBag{
	protected $originalWidth = 0;
	protected $originalHeight = 0;
	protected $originalFileSize = 0;
	protected $originalName = null;
	protected $assetPath = null;
	protected $exifData = null;

	public function __construct($assetPath,$originalName){
		$this->assetPath = $assetPath;
		$this->originalName = $originalName;
		$this->loadOriginalFileSize();
		$this->loadDimensions();
		$this->loadExifData();
	}

	protected function loadExifData(){
		/// only available for jpeg images
        if(preg_match('/\.jp[e]?g$/i', $this->assetPath)){
            $this->exifData = exif_read_data($this->assetPath);
        }
	}

	protected function loadDimensions(){
		$imagesize = getimagesize($this->assetPath);
        $this->originalWidth = $imagesize[0];
        $this->originalHeight = $imagesize[1];
	}

	protected function loadOriginalFileSize(){
		$this->originalFileSize = filesize($this->assetPath);
	}

	public function getOriginalWidth(){
		return $this->originalWidth;
	}
	
	public function getOriginalHeight(){
		return $this->originalHeight;
	}
	
	public function getOriginalFileSize(){
		return $this->originalFileSize;
	}
	
	public function getOriginalName(){
		return $this->originalName;
	}
	
	public function getAssetPath(){
		return $this->assetPath;
	}
	
	public function getExifData(){
		return $this->exifData;
	}
	

}
