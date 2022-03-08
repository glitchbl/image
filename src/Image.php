<?php

namespace Glitchbl;

use Exception;

/**
 * @property-read mixed $resource
 * @property-read int $width
 * @property-read int $height
 * @property-read string $extension
 * @property-read string $file
 * @property-read string $bytes
 */
class Image
{
    const   GIF = 'gif',
            PNG = 'png',
            JPG = 'jpg';

    /**
     * @var mixed Image resource
     */
    protected $resource = null;

    /**
     * @var int Image width
     */
    protected $width;

    /**
     * @var int Image height
     */
    protected $height;

    /**
     * @var string Image extension
     */
    protected $extension;

    /**
     * @var string Image path
     */
    protected $file;

    /**
     * @param string $file Image path
     */
    public function __construct($file)
    {
        if (!is_file($file))
            throw new Exception("{$file} is not a file");

        list(,, $image_type) = getimagesize($file);

        switch ($image_type) {
            case IMAGETYPE_GIF:
                $this->resource = imagecreatefromgif($file);
                $this->extension = self::GIF;
                break;
            case IMAGETYPE_JPEG:
                $this->resource = imagecreatefromjpeg($file);
                $this->extension = self::JPG;
                break;
            case IMAGETYPE_PNG:
                $this->resource = imagecreatefrompng($file);
                $this->extension = self::PNG;
                break;
        }

        $exif = @exif_read_data($file);
        $orientation = isset($exif['Orientation']) ? $exif['Orientation'] : 0;

        switch ($orientation) {
            case 2:
                imageflip($this->resource, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $this->resource = imagerotate($this->resource, 180, 0);
                break;
            case 4:
                imageflip($this->resource, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $this->resource = imagerotate($this->resource, -90, 0);
                imageflip($this->resource, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $this->resource = imagerotate($this->resource, -90, 0);
                break;
            case 7:
                $this->resource = imagerotate($this->resource, 90, 0);
                imageflip($this->resource, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $this->resource = imagerotate($this->resource, 90, 0);
                break;
        }

        $this->width = imagesx($this->resource);
        $this->height = imagesy($this->resource);

        $this->file = $file;
    }

    /**
     * @param int $width
     * @param int $height
     * @return \GdImage
     */
    protected function createTransparentResource($width, $height)
    {
        $newImageResource = imagecreatetruecolor($width, $height);
        $transparentColor = imagecolorallocatealpha($newImageResource, 0, 0, 0, 127);
        imagefill($newImageResource, 0, 0, $transparentColor);
        return $newImageResource;
    }

    /**
     * @param string $destination Image location
     * @param string $extension Image extention (PNG or JPG)
     */
    protected function _save($destination, $extension)
    {
        if ($extension == static::PNG) {
            imagesavealpha($this->resource, true);
            imagepng($this->resource, $destination, 6);
        } else {
            imagejpeg($this->resource, $destination);
        }
    }

    /**
     * @param string|null $extension
     * @return string
     */
    protected function getBytes($extension = null)
    {
        $extension = $extension ?? $this->extension;

        ob_start();

        $this->_save(null, $extension);

        return ob_get_clean();
    }

    /**
     * @param string|null $destination Image location
     * @param string|null $extension Image extention (PNG or JPG)
     */
    public function save($destination = null, $extension = null)
    {
        $this->_save($destination ?? $this->file, $extension ?? $this->extension);
    }

    /**
     * Read-only getters
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        if ($name == 'bytes')
            return $this->getBytes();
        else
            return $this->{$name};
    }

    /**
     * @return mixed Image
     */
    public function clone()
    {
        $width = imagesx($this->resource);
        $height = imagesy($this->resource);
        $transparancy = imagecolortransparent($this->resource);

        if (imageistruecolor($this->resource)) {
            $clone = imagecreatetruecolor($width, $height);
            imagealphablending($clone, false);
            imagesavealpha($clone, true);
        } else {
            $clone = imagecreate($width, $height);

            if ($transparancy >= 0) {
                $rgb = imagecolorsforindex($this->resource, $transparancy);
                imagesavealpha($clone, true);
                $transparancyIndex = imagecolorallocatealpha($clone, $rgb['red'], $rgb['green'], $rgb['blue'], $rgb['alpha']);
                imagefill($clone, 0, 0, $transparancyIndex);
            }
        }

        imagecopy($clone, $this->resource, 0, 0, 0, 0, $width, $height);

        return $clone;
    }

    /**
     * @param int $type Filter type
     * @param void
     */
    public function filter($type)
    {
        imagefilter($this->resource, $type);
    }

    /**
     * @param string $fontPath
     * @param string $text
     * @param int $x
     * @param int $y
     * @param array $align
     * @param int|string $fontSize
     * @param array $textColor
     * @param array $bgColor
     * @param int $padding
     */
    public function addText($fontPath, $text, $x = 0, $y = 0, $align = ['top', 'left'], $fontSize = 'auto', $textColor = [255, 255, 255], $bgColor = [255, 255, 255, 127], $padding = 0)
    {
        if (!is_file($fontPath))
            throw new Exception("{$fontPath} does not exist");

        $width = $this->width;

        if ($fontSize == 'auto') {
            for ($i = 128; $i > 0; $i--) {
                $bbox = imagettfbbox($i, 0, $fontPath, $text);
                $textWidth = 0 - $bbox[0] + $bbox[2] + $padding * 2;
                if ($textWidth < $width) {
                    $fontSize = $i;
                    break;
                }
            }
        }

        $bbox = imagettfbbox((int)$fontSize, 0, $fontPath, $text);
        $height = 0 - $bbox[5] + $bbox[1] + $padding * 2;

        $imageText = imagecreatetruecolor($width, $height);

        $imageBgColor = imagecolorallocatealpha($imageText, $bgColor[0], $bgColor[1], $bgColor[2], $bgColor[3]);
        imagefill($imageText, 0, 0, $imageBgColor);

        if ($align[1] == 'center') {
            $newX = round(($width - $bbox[2]) / 2);
        } elseif ($align[1] == 'right') {
            $newX = $width - $bbox[2] - $x;
        } else {
            $newX = $x;
        }

        imagettftext($imageText, $fontSize, 0, $newX, 0 - $bbox[5] + $padding, imagecolorallocate($imageText, $textColor[0], $textColor[1], $textColor[2]), $fontPath, $text);

        $this->addImage($imageText, 0, $y, $align);

        $width = imagesx($imageText);
        $height = imagesy($imageText);

        imagedestroy($imageText);

        return [$width, $height];
    }

    /**
     * @param mixed $image
     * @param int $x
     * @param int $y
     * @param array $align
     */
    public function addImage($image, $x = 0, $y = 0, $align = ['top', 'left'])
    {
        if ($image instanceof self) {
            $resource = $image->resource;
            $width = $image->width;
            $height = $image->height;
        } else {
            $resource = $image;
            $width = imagesx($image);
            $height = imagesy($image);
        }

        if ($align[1] == 'center') {
            $newX = round(($this->width - $width) / 2);
        } elseif ($align[1] == 'right') {
            $newX = $this->width - $width - $x;
        } else {
            $newX = $x;
        }

        if ($align[0] == 'center') {
            $newY = round(($this->height - $height) / 2);
        } elseif ($align[0] == 'bottom') {
            $newY = $this->height - $height - $y;
        } else {
            $newY = $y;
        }

        $newImageResource = $this->createTransparentResource($this->width, $this->height);

        imagecopy($newImageResource, $this->resource, 0, 0, 0, 0, $this->width, $this->height);

        imagecopy($newImageResource, $resource, $newX, $newY, 0, 0, $width, $height);

        imagedestroy($this->resource);
        $this->resource = $newImageResource;
    }

    /**
     * @param int $maxWidth Max resized image width
     * @param int $maxHeight Max resized image height
     * @return void
     */
    public function resize($maxWidth = 1000, $maxHeight = 1000)
    {
        $width = $this->width;
        $height = $this->height;

        if ($this->width / $this->height >= 1) {
            if ($this->width > $maxWidth) {
                $ratio = $maxWidth / $this->width;
                $width = $maxWidth;
                $height = (int)($this->height * $ratio);
            }
            if ($height > $maxHeight) {
                $ratio = $maxHeight / $height;
                $height = $maxHeight;
                $width = (int)($width * $ratio);
            }
        } else {
            if ($this->height > $maxHeight) {
                $ratio = $maxHeight / $this->height;
                $height = $maxHeight;
                $width = (int)($this->width * $ratio);
            }
            if ($width > $maxWidth) {
                $ratio = $maxWidth / $width;
                $width = $maxWidth;
                $height = (int)($height * $ratio);
            }
        }

        $newImageResource = $this->createTransparentResource($width, $height);
        imagecopyresampled($newImageResource, $this->resource, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

        imagedestroy($this->resource);
        $this->resource = $newImageResource;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * @param int $width Crop width
     * @param int $height Crop height
     * @param int $x Crop start X
     * @param int $y Crop start Y
     * @return void
     */
    public function crop($width, $height, $x = 0, $y = 0)
    {
        if ($width > $this->width)
            $width = $this->width;

        if ($height > $this->height)
            $height = $this->height;

        if ($x < 0)
            $x = 0;
        elseif ($width + $x > $this->width)
            $x = $this->width - $width;

        if ($y < 0)
            $y = 0;
        elseif ($y + $height > $this->height)
            $y = $this->height - $height;

        $newImageResource = $this->createTransparentResource($width, $height);
        imagecopy($newImageResource, $this->resource, 0, 0, $x, $y, $width, $height);

        imagedestroy($this->resource);
        $this->resource = $newImageResource;
        $this->width = $width;
        $this->height = $height;
    }
    /**
     * @param string $destination Thumb location
     * @param int $width Thumb width
     * @param int $height Thumb height
     * @param int $offsetX Thumb X Offset
     * @param int $offsetY Thumb Y Offset
     * @return void
     */
    public function thumb($width, $height, $offsetX = 0, $offsetY = 0)
    {
        $cutWidth = $this->width;
        $cutHeight = $this->height;

        $ratioHeightToWidth = $width / $height;
        $ratioWidthToHeight = $height / $width;

        if ($cutWidth <= $cutHeight)
            $cutHeight = (int)($cutWidth * $ratioWidthToHeight);
        else
            $cutWidth = (int)($cutHeight * $ratioHeightToWidth);

        if ($cutWidth > $this->width) {
            $ratio = $this->width / $cutWidth;
            $cutWidth = $this->width;
            $cutHeight = (int)($ratio * $cutHeight);
        }

        if ($cutHeight > $this->height) {
            $ratio = $this->height / $cutHeight;
            $cutHeight = $this->height;
            $cutWidth = (int)($ratio * $cutWidth);
        }

        $startX = round((($this->width - $cutWidth) / 2) + $offsetX);
        if ($startX < 0)
            $startX = 0;
        elseif ($startX + $cutWidth > $this->width)
            $startX = $this->width - $cutWidth;

        $startY = round((($this->height - $cutHeight) / 2) + $offsetY);
        if ($startY < 0)
            $startY = 0;
        elseif ($startY + $cutHeight > $this->height)
            $startY = $this->height - $cutHeight;

        $newImageResource = $this->createTransparentResource($width, $height);
        imagecopyresampled($newImageResource, $this->resource, 0, 0, $startX, $startY, $width, $height, $cutWidth, $cutHeight);

        imagedestroy($this->resource);
        $this->resource = $newImageResource;
        $this->width = $width;
        $this->height = $height;
    }
}
