<?php
declare(strict_types=1);

namespace WernerDweight\ImageManager\Image;

class Image
{
    /** @var string */
    private const DEFAULT_ENCRYPTION_METHOD = 'AES-256-CBC';
    /** @var int */
    private const ORIENTATION_UPSIDE_DOWN = 3;
    /** @var int */
    private const ORIENTATION_ROTATE_COUNTER_CLOCKWISE = 6;
    /** @var int */
    private const ORIENTATION_ROTATE_CLOCKWISE = 8;
    /** @var string */
    private const FORMAT_JPEG = 'jpeg';
    /** @var string */
    private const FORMAT_JPG = 'jpg';
    /** @var string */
    private const FORMAT_PNG = 'png';
    /** @var string */
    private const FORMAT_GIF = 'gif';
    /** @var string */
    private const FORMAT_WD_IMAGE = 'wdImage';
    /** @var string[] */
    private const EXTENSION_MAPPING = [
        'jpg' => self::FORMAT_JPEG,
        'jpeg' => self::FORMAT_JPEG,
        'png' => self::FORMAT_PNG,
        'gif' => self::FORMAT_GIF,
        'wdimage' => self::FORMAT_WD_IMAGE,
    ];

    /** @var int */
    private $width;

    /** @var int */
    private $height;

    /** @var string|null */
    private $extension;

    /** @var resource */
    private $workingData;

    /** @var string */
    private $secret;

    /** @var bool */
    private $encrypted;

    /** @var bool */
    private $autorotate;

    /** @var string */
    private $encryptionMethod;

    /**
     * Image constructor.
     * @param string $secret
     * @param null|string $path
     * @param null|string $extension
     * @param bool $autorotate
     * @param string $encryptionMethod
     */
    public function __construct(
        string $secret,
        ?string $path = null,
        ?string $extension = null,
        bool $autorotate = false,
        string $encryptionMethod = self::DEFAULT_ENCRYPTION_METHOD
    ) {
        $this->secret = $secret;
        $this->autorotate = $autorotate;
        $this->encryptionMethod = $encryptionMethod;
        if (null !== $path) {
            $this->load($path);
        }
        if (null !== $extension) {
            $this->extension = strtolower($extension);
        }
    }

    /**
     * @param int $width
     * @param int $height
     * @return Image
     */
    public function create(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;
        $imageData = imagecreatetruecolor($this->width, $this->height);
        if (false === $imageData) {
            throw new \RuntimeException('Unable to create new image!');
        }
        $this->workingData = $imageData;
        if (self::FORMAT_PNG === $this->extension) {
            $this->setTransparency(false, true);
        }
        if (self::FORMAT_GIF === $this->extension) {
            $this->setTransparency();
        }
        $this->encrypted = false;
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function autoRotate(string $path): self
    {
        $exifData = exif_read_data($path);
        if (true === array_key_exists('Orientation', $exifData)) {
            $imageData = null;
            switch ($exifData['Orientation']) {
                case self::ORIENTATION_UPSIDE_DOWN:
                    $imageData = imagerotate($this->workingData, 180, 0);
                    break;
                case self::ORIENTATION_ROTATE_COUNTER_CLOCKWISE:
                    $imageData = imagerotate($this->workingData, -90, 0);
                    break;
                case self::ORIENTATION_ROTATE_CLOCKWISE:
                    $imageData = imagerotate($this->workingData, 90, 0);
                    break;
            }
            if (null !== $imageData) {
                $this->workingData = $imageData;
            }
        }
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function createFromJpeg(string $path): self
    {
        $imageData = imagecreatefromjpeg($path);
        if (false === $imageData) {
            throw new \RuntimeException(sprintf('Unable to create JPEG image from %s!', $path));
        }
        $this->workingData = $imageData;
        $this->encrypted = false;
        if (true === $this->autorotate) {
            $this->autoRotate($path);
        }
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function createFromPng(string $path): self
    {
        $imageData = imagecreatefrompng($path);
        if (false === $imageData) {
            throw new \RuntimeException(sprintf('Unable to create PNG image from %s!', $path));
        }
        $this->workingData = $imageData;
        $this->setTransparency(false, true);
        $this->encrypted = false;
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function createFromGif(string $path): self
    {
        $imageData = imagecreatefromgif($path);
        if (false === $imageData) {
            throw new \RuntimeException(sprintf('Unable to create GIF image from %s!', $path));
        }
        $this->workingData = $imageData;
        $this->setTransparency();
        $this->encrypted = false;
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function createFromEncrypted(string $path): self
    {
        /** @var resource $imageData */
        $imageData = file_get_contents($path);
        $this->workingData = $imageData;
        $this->encrypted = true;
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function setExtensionFromPath(string $path): self
    {
        $lastDotPosition = strrchr($path, '.');
        if (false === $lastDotPosition) {
            throw new \RuntimeException('File extension is missing!');
        }

        $originalExtension = strtolower(substr($lastDotPosition, 1));
        if (false === array_key_exists($originalExtension, self::EXTENSION_MAPPING)) {
            throw new \RuntimeException(sprintf('Unsupported image format %s!', $originalExtension));
        }

        $this->extension = self::EXTENSION_MAPPING[$originalExtension];
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function loadFromPath(string $path): self
    {
        switch ($this->extension) {
            case self::FORMAT_JPEG:
                $this->createFromJpeg($path);
                break;
            case self::FORMAT_PNG:
                $this->createFromPng($path);
                break;
            case self::FORMAT_GIF:
                $this->createFromGif($path);
                break;
            case self::FORMAT_WD_IMAGE:
                $this->createFromEncrypted($path);
                break;
            default:
                throw new \RuntimeException('This image format is not supported!');
        }
        return $this;
    }

    /**
     * @param string $path
     * @return Image
     */
    private function load(string $path): self
    {
        return $this
            ->setExtensionFromPath($path)
            ->loadFromPath($path)
            ->updateDimensions();
    }

    /**
     * @return Image
     */
    private function updateDimensions(): self
    {
        if (true !== $this->encrypted) {
            $this->width = imagesx($this->workingData);
            $this->height = imagesy($this->workingData);
        }
        return $this;
    }

    /**
     * @param string $path
     * @param string $name
     * @param null|string $extension
     * @param int $quality
     * @return bool
     */
    public function save(string $path, string $name, ?string $extension = null, int $quality = 100): bool
    {
        if (false === is_dir($path)) {
            mkdir($path, 0777, true);
        }
        if (null === $extension) {
            /** @var string $extension */
            $extension = $this->extension;
        }
        if (true === $this->encrypted) {
            file_put_contents($path . $name . '.' . self::FORMAT_WD_IMAGE, $this->workingData);
            return true;
        } elseif (0 !== imagetypes()) {
            $filename = $path . $name . '.' . $extension;
            switch (strtolower($extension)) {
                case self::FORMAT_GIF:
                    imagegif($this->workingData, $filename);
                    break;
                case self::FORMAT_JPEG:
                case self::FORMAT_JPG:
                    imagejpeg($this->workingData, $filename, $quality);
                    break;
                case self::FORMAT_PNG:
                    imagepng($this->workingData, $filename, (int)round(9 - ((9 * $quality) / 100)));
                    break;
                default:
                    throw new \RuntimeException('Unsupported file type');
            }
            return false;
        }
    }

    /**
     * @return int
     */
    private function getInitializationVectorLength(): int
    {
        $initializationVectorLength = openssl_cipher_iv_length($this->encryptionMethod);
        if (false === $initializationVectorLength) {
            throw new \RuntimeException('Unable to create initialization vector! Check encryption method.');
        } else {
            return $initializationVectorLength;
        }
    }

    /**
     * @return string
     */
    private function createInitializationVector(): string
    {
        $initializationVector = openssl_random_pseudo_bytes(
            $this->getInitializationVectorLength()
        );
        if (false === $initializationVector) {
            throw new \RuntimeException('Unable to create initialization vector!');
        } else {
            return $initializationVector;
        }
    }

    /**
     * @return Image
     */
    public function encrypt(): self
    {
        if ($this->encrypted) {
            throw new \RuntimeException('Unable to encrypt encrypted image!');
        }

        // use buffer to get image content
        ob_start();
        imagejpeg($this->workingData, null, 100);
        $imageData = ob_get_contents();
        ob_end_clean();
        if (false === $imageData) {
            throw new \RuntimeException('Unable to fetch image data!');
        }

        $initializationVector = $this->createInitializationVector();
        $encryptedData = openssl_encrypt(
            $imageData,
            $this->encryptionMethod,
            $this->secret,
            OPENSSL_RAW_DATA,
            $initializationVector
        );
        if (false === $encryptedData) {
            throw new \RuntimeException('Data encryption failed!');
        }
        /** @var resource $encodedData */
        $encodedData = base64_encode($initializationVector . rtrim($encryptedData, "\0"));
        $this->workingData = $encodedData;
        $this->encrypted = true;

        return $this;
    }

    /**
     * @return Image
     */
    public function decrypt(): self
    {
        if (!$this->encrypted) {
            throw new \RuntimeException('Unable to decrypt unencrypted image');
        }

        $encodedData = (string)$this->workingData;
        $decodedData = base64_decode($encodedData);
        if (false === $decodedData) {
            throw new \RuntimeException('Unable to decode image data!');
        }

        $initializationVectorLength = $this->getInitializationVectorLength();
        $initializationVector = substr($decodedData, 0, $initializationVectorLength);
        $decodedImageData = substr($decodedData, $initializationVectorLength);
        $decryptedData = openssl_decrypt(
            $decodedImageData,
            $this->encryptionMethod,
            $this->secret,
            OPENSSL_RAW_DATA,
            $initializationVector
        );
        if (false === $decryptedData) {
            throw new \RuntimeException('Unable to decrypt image data!');
        }

        $decryptedImageData = rtrim($decryptedData, "\0");
        $this->encrypted = false;

        $imageData = imagecreatefromstring($decryptedImageData);
        if (false === $imageData) {
            throw new \RuntimeException('Unable to create image from string!');
        }

        $this->workingData = $imageData;

        if (null === $this->width || null === $this->height) {
            $this->updateDimensions();
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getEncrypted(): bool
    {
        return $this->encrypted;
    }

    /**
     * @return Image
     */
    public function destroy(): self
    {
        imagedestroy($this->workingData);
        return $this;
    }

    /**
     * @return resource
     */
    public function getData()
    {
        return $this->workingData;
    }

    /**
     * @param resource $data
     * @return Image
     */
    public function setData($data): self
    {
        $this->workingData = $data;
        return $this;
    }

    /**
     * @return int
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * @return null|string
     */
    public function getExtension(): ?string
    {
        return $this->extension;
    }

    /**
     * @param bool|null $alphaBlending
     * @param bool|null $saveAlpha
     * @return Image
     */
    private function setTransparency(?bool $alphaBlending = null, ?bool $saveAlpha = null): self
    {
        imagecolortransparent($this->workingData, imagecolorallocate($this->workingData, 0, 0, 0));
        if (null !== $alphaBlending) {
            imagealphablending($this->workingData, $alphaBlending);
        }
        if (null !== $saveAlpha) {
            imagesavealpha($this->workingData, $saveAlpha);
        }
        return $this;
    }
}
