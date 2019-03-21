<?php
declare(strict_types=1);

namespace WernerDweight\ImageManager\Image;

class ProcessedImageBag
{
    /** @var string */
    private const EXIF_EXTENSION_WILDCARD = '/\.jp[e]?g$/i';
    /** @var int */
    private const ORIENTATION_ROTATE_COUNTER_CLOCKWISE = 6;
    /** @var int */
    private const ORIENTATION_ROTATE_CLOCKWISE = 8;

    /** @var int */
    protected $originalWidth = 0;

    /** @var int */
    protected $originalHeight = 0;

    /** @var int */
    protected $originalFileSize = 0;

    /** @var string */
    protected $originalName;

    /** @var string */
    protected $assetPath;

    /** @var array */
    protected $exifData = [];

    /** @var bool */
    protected $autorotate;

    /**
     * ProcessedImageBag constructor.
     *
     * @param string $assetPath
     * @param string $originalName
     * @param bool   $autorotate
     */
    public function __construct(string $assetPath, string $originalName, bool $autorotate = false)
    {
        $this->assetPath = $assetPath;
        $this->originalName = $originalName;
        $this->autorotate = $autorotate;
        $this
            ->loadOriginalFileSize()
            ->loadDimensions()
            ->loadExifData();
    }

    /**
     * @return ProcessedImageBag
     */
    protected function loadExifData(): self
    {
        // only available for jpeg images
        if (preg_match(self::EXIF_EXTENSION_WILDCARD, $this->assetPath)) {
            $exifData = exif_read_data($this->assetPath);
            if (false !== $exifData) {
                $this->exifData = $exifData;
                // fix rotation if autorotate is true
                if (true === $this->autorotate && true === array_key_exists('Orientation', $this->exifData)) {
                    switch ($this->exifData['Orientation']) {
                        case self::ORIENTATION_ROTATE_COUNTER_CLOCKWISE:
                        case self::ORIENTATION_ROTATE_CLOCKWISE:
                            $tmpWidth = $this->originalWidth;
                            $this->originalWidth = $this->originalHeight;
                            $this->originalHeight = $tmpWidth;
                            break;
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @return ProcessedImageBag
     */
    protected function loadDimensions(): self
    {
        $imagesize = getimagesize($this->assetPath);
        [$this->originalWidth, $this->originalHeight] = $imagesize;
        return $this;
    }

    /**
     * @return ProcessedImageBag
     */
    protected function loadOriginalFileSize(): self
    {
        $this->originalFileSize = (int)filesize($this->assetPath);
        return $this;
    }

    /**
     * @return int
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * @return int
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * @return int
     */
    public function getOriginalFileSize(): int
    {
        return $this->originalFileSize;
    }

    /**
     * @return string
     */
    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    /**
     * @return string
     */
    public function getAssetPath(): string
    {
        return $this->assetPath;
    }

    /**
     * @return array|null
     */
    public function getExifData(): ?array
    {
        return $this->exifData;
    }
}
