<?php
declare(strict_types=1);

namespace WernerDweight\ImageManager\Manager;

use WernerDweight\ImageManager\Image\Image;
use WernerDweight\ImageManagerBundle\Service\ImageManagerUtility;

class ImageManager
{
    /** @var string */
    private const WATERMARK_SIZE_COVER = 'cover';
    /** @var string */
    private const WATERMARK_SIZE_CONTAIN = 'contain';

    /** @var Image */
    private $image;

    /** @var string */
    private $secret;

    /** @var bool */
    private $autorotate;

    /**
     * ImageManager constructor.
     * @param string $secret
     * @param bool $autorotate
     */
    public function __construct(string $secret, bool $autorotate = false)
    {
        $this->secret = $secret;
        $this->autorotate = $autorotate;
    }

    /**
     * @param string $path
     * @return ImageManager
     */
    public function loadImage(string $path): self
    {
        $this->image = new Image($this->secret, $path, null, $this->autorotate);
        return $this;
    }

    /**
     * @param string $path
     * @param string $name
     * @param null|string $extension
     * @param int $quality
     * @return ImageManager
     */
    public function saveImage(string $path, string $name, ?string $extension = null, int $quality = 100): self
    {
        $this->image->save($path, $name, $extension, $quality);
        return $this;
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return Image
     */
    public function resizeImage(Image $image, int $width, int $height, bool $crop = false): Image
    {
        if (true === $image->getEncrypted()) {
            $this->decryptImage($image);
            $encrypt = true;
        } else {
            $encrypt = false;
        }

        $dimensions = $this->getAdjustedImageDimensions($image, $width, $height, $crop);
        $tmp = new Image($this->secret, null, $image->getExtension(), $this->autorotate);
        $tmp->create($dimensions['width'], $dimensions['height']);
        imagecopyresampled(
            $tmp->getData(),
            $image->getData(),
            0,
            0,
            0,
            0,
            $dimensions['width'],
            $dimensions['height'],
            $image->getWidth(),
            $image->getHeight()
        );
        $image->destroy();
        $image = $tmp;

        if (true === $crop) {
            $image = $this->cropImage($image, $width, $height);
        }

        if (true === $encrypt) {
            $image = $this->encryptImage($image);
        }
        return $image;
    }

    /**
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return ImageManager
     */
    public function resize(int $width, int $height, bool $crop = false): self
    {
        $this->image = $this->resizeImage($this->image, $width, $height, $crop);
        return $this;
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @return Image
     */
    public function cropImage(Image $image, int $width, int $height): Image
    {
        if (true === $image->getEncrypted()) {
            $this->decryptImage($image);
            $encrypt = true;
        } else {
            $encrypt = false;
        }

        $crop = $this->getAdjustedImageCropDimensions($image, $width, $height);
        $centerX = ($image->getWidth() / 2) - ($crop['width'] / 2);
        $centerY = ($image->getHeight() / 2) - ($crop['height'] / 2);
        $tmp = new Image($this->secret, null, $image->getExtension(), $this->autorotate);
        $tmp->create($width, $height);
        imagecopyresampled(
            $tmp->getData(),
            $image->getData(),
            0,
            0,
            (int)$centerX,
            (int)$centerY,
            $width,
            $height,
            $crop['width'],
            $crop['height']
        );
        $image->destroy();
        $image = $tmp;

        if (true === $encrypt) {
            $image = $this->encryptImage($image);
        }
        return $image;
    }

    /**
     * @param int $width
     * @param int $height
     * @return ImageManager
     */
    public function crop(int $width, int $height): self
    {
        $this->image = $this->cropImage($this->image, $width, $height);
        return $this;
    }

    /**
     * @param Image $image
     * @return Image
     */
    public function encryptImage(Image $image): Image
    {
        return $image->encrypt();
    }

    /**
     * @return ImageManager
     */
    public function encrypt(): self
    {
        $this->image = $this->encryptImage($this->image);
        return $this;
    }

    /**
     * @param Image $image
     * @return Image
     */
    public function decryptImage(Image $image): Image
    {
        return $image->decrypt();
    }

    /**
     * @return ImageManager
     */
    public function decrypt(): self
    {
        $this->image = $this->decryptImage($this->image);
        return $this;
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @param bool $relative
     * @return array
     */
    private function getAdjustedImageCropDimensions(Image $image, int $width, int $height, bool $relative = false): array
    {
        $w = $image->getWidth() / $width;
        $h = $image->getHeight() / $height;

        if ($w < 1 || $h < 1) {
            if ($w < $h) {
                return [
                    'width' => $width * $w,
                    'height' => $height * $w,
                ];
            } else {
                return [
                    'width' => $width * $h,
                    'height' => $height * $h,
                ];
            }
        } else {
            if ($w < $h) {
                return [
                    'width' => $width * (true === $relative ? $w : 1),
                    'height' => $height * (true === $relative ? $w : 1),
                ];
            } else {
                return [
                    'width' => $width * (true === $relative ? $h : 1),
                    'height' => $height * (true === $relative ? $h : 1),
                ];
            }
        }
    }

    /**
     * @param Image $image
     * @param int $width
     * @param int $height
     * @param bool $crop
     * @return array
     */
    private function getAdjustedImageDimensions(Image $image, int $width, int $height, bool $crop = false): array
    {
        if ($image->getWidth() / $image->getHeight() > $width / $height) {		// current is wider than new
            if (true === $crop) {	// upscale prevention
                return [
                    'width' => $this->getImageWidth($image, $height),
                    'height' => $height,
                ];
            } else {
                return [
                    'width' => $width,
                    'height' => $this->getImageHeight($image, $width),
                ];
            }
        } elseif ($image->getWidth() / $image->getHeight() < $width / $height) {	// current is taller than new
            if (true === $crop) {	// upscale prevention
                return [
                    'width' => $width,
                    'height' => $this->getImageHeight($image, $width),
                ];
            } else {
                return [
                    'width' => $this->getImageWidth($image, $height),
                    'height' => $height,
                ];
            }
        } else {									// current has same aspect ratio as new
            return [
                'width' => $width,
                'height' => $height,
            ];
        }
    }

    /**
     * @param Image $image
     * @param array $parameters
     * @return Image
     */
    public function addImageWatermark(Image $image, array $parameters): Image
    {
        $watermark = new Image($this->secret, $parameters['file'], null, $this->autorotate);

        // temporarily enable alphablending to be able to use 32-bit images
        imagealphablending($watermark->getData(), true);
        imagealphablending($image->getData(), true);

        // determine watermark position from config
        if (true === isset($parameters['position'])) {
            $top = intval($parameters['position']['top']) / 100;
            $left = intval($parameters['position']['left']) / 100;
        } else {
            $top = $left = 1;
        }

        // determine watermark size from config
        if (true === isset($parameters['size'])) {
            if (self::WATERMARK_SIZE_COVER === $parameters['size']) {
                // cover dimensions are the same as crop dimensions
                $dimensions = $this->getAdjustedImageCropDimensions($watermark, $image->getWidth(), $image->getHeight(), true);
                imagecopyresampled(
                    $image->getData(),
                    $watermark->getData(),
                    0,
                    0,
                    (int)(($watermark->getWidth() - $dimensions['width']) * $left),
                    (int)(($watermark->getHeight() - $dimensions['height']) * $top),
                    $image->getWidth(),
                    $image->getHeight(),
                    $dimensions['width'],
                    $dimensions['height']
                );
            } elseif (self::WATERMARK_SIZE_CONTAIN === $parameters['size']) {
                $dimensions = $this->getAdjustedImageDimensions($watermark, $image->getWidth(), $image->getHeight());
                imagecopyresampled(
                    $image->getData(),
                    $watermark->getData(),
                    (int)(($image->getWidth() - $dimensions['width']) * $left),
                    (int)(($image->getHeight() - $dimensions['height']) * $top),
                    0,
                    0,
                    $dimensions['width'],
                    $dimensions['height'],
                    $watermark->getWidth(),
                    $watermark->getHeight()
                );
            } else {		// percentage
                $dimensions = $this->getAdjustedImageDimensions($watermark, $image->getWidth(), $image->getHeight());
                $dimensions['width'] *= (intval($parameters['size']) / 100);
                $dimensions['height'] *= (intval($parameters['size']) / 100);
                imagecopyresampled(
                    $image->getData(),
                    $watermark->getData(),
                    (int)(($image->getWidth() - $dimensions['width']) * $left),
                    (int)(($image->getHeight() - $dimensions['height']) * $top),
                    0,
                    0,
                    $dimensions['width'],
                    $dimensions['height'],
                    $watermark->getWidth(),
                    $watermark->getHeight()
                );
            }
        } else {
            imagecopy(
                $image->getData(),
                $watermark->getData(),
                (int)(($image->getWidth() - $watermark->getWidth()) * $left),
                (int)(($image->getHeight() - $watermark->getHeight()) * $top),
                0,
                0,
                $watermark->getWidth(),
                $watermark->getHeight()
            );
        }

        // disable alphablending again to be able to save transparency
        imagealphablending($watermark->getData(), false);
        imagealphablending($image->getData(), false);

        return $image;
    }

    /**
     * @param array $parameters
     * @return ImageManager
     */
    public function addWatermark(array $parameters): self
    {
        $this->image = $this->addImageWatermark($this->image, $parameters);
        return $this;
    }

    /**
     * @param Image $image
     * @param int $height
     * @return int
     */
    private function getImageWidth(Image $image, int $height): int
    {
        return (int)($height * ($image->getWidth() / $image->getHeight()));
    }

    /**
     * @param Image $image
     * @param int $width
     * @return int
     */
    private function getImageHeight(Image $image, int $width): int
    {
        return (int)($width * ($image->getHeight() / $image->getWidth()));
    }
}
