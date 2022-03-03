<?php

namespace Glitchbl;

use Exception;

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
                imageAlphaBlending($this->resource, true);
                imageSaveAlpha($this->resource, true);
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
     * Read-only getters
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
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
     * @param int $fontSize
     * @param array $textColor
     * @param array $bgColor
     * @param int $padding
     */
    public function addText($fontPath, $text, $x = 0, $y = 0, $align = ['top', 'left'], $fontSize = 32, $textColor = [255, 255, 255], $bgColor = [255, 255, 255, 127], $padding = 0)
    {
        if (!is_file($fontPath))
            throw new Exception("{$fontPath} does not exist");

        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $width = $this->width;
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

        $newImageResource = imagecreatetruecolor($this->width, $this->height);
        $transparentColor = imagecolorallocatealpha($newImageResource, 0, 0, 0, 127);
        imagefill($newImageResource, 0, 0, $transparentColor);

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
        $dstWidth = $this->width;
        $dstHeight = $this->height;

        if ($this->width / $this->height >= 1) {
            if ($this->width > $maxWidth) {
                $ratio = $maxWidth / $this->width;
                $dstWidth = $maxWidth;
                $dstHeight = (int)($this->height * $ratio);
            }
            if ($dstHeight > $maxHeight) {
                $ratio = $maxHeight / $dstHeight;
                $dstHeight = $maxHeight;
                $dstWidth = (int)($dstWidth * $ratio);
            }
        } else {
            if ($this->height > $maxHeight) {
                $ratio = $maxHeight / $this->height;
                $dstHeight = $maxHeight;
                $dstWidth = (int)($this->width * $ratio);
            }
            if ($dstWidth > $maxWidth) {
                $ratio = $maxWidth / $dstWidth;
                $dstWidth = $maxWidth;
                $dstHeight = (int)($dstHeight * $ratio);
            }
        }

        $dstResource = imagecreatetruecolor($dstWidth, $dstHeight);
        $white = imagecolorallocate($dstResource, 255, 255, 255);
        imagefill($dstResource, 0, 0, $white);
        imagecopyresampled($dstResource, $this->resource, 0, 0, 0, 0, $dstWidth, $dstHeight, $this->width, $this->height);

        imagedestroy($this->resource);
        $this->resource = $dstResource;
        $this->width = $dstWidth;
        $this->height = $dstHeight;
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

        $dstResource = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($dstResource, 255, 255, 255);
        imagefill($dstResource, 0, 0, $white);
        imagecopy($dstResource, $this->resource, 0, 0, $x, $y, $width, $height);

        imagedestroy($this->resource);
        $this->resource = $dstResource;
        $this->width = $width;
        $this->height = $height;
    }
    /**
     * @param string $destination Thumb location
     * @param int $thumbWidth Thumb width
     * @param int $thumbHeight Thumb height
     * @param int $offsetX Thumb X Offset
     * @param int $offsetY Thumb Y Offset
     * @return void
     */
    public function thumb($thumbWidth, $thumbHeight, $offsetX = 0, $offsetY = 0)
    {
        $cutWidth = $this->width;
        $cutHeight = $this->height;

        $ratioHeightToWidth = $thumbWidth / $thumbHeight;
        $ratioWidthToHeight = $thumbHeight / $thumbWidth;

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

        $dstResource = imagecreatetruecolor($thumbWidth, $thumbHeight);
        $white = imagecolorallocate($dstResource, 255, 255, 255);
        imagefill($dstResource, 0, 0, $white);
        imagecopyresampled($dstResource, $this->resource, 0, 0, $startX, $startY, $thumbWidth, $thumbHeight, $cutWidth, $cutHeight);

        imagedestroy($this->resource);
        $this->resource = $dstResource;
        $this->width = $thumbWidth;
        $this->height = $thumbHeight;
    }

    /**
     * @param string|null $destination Image location
     * @param string|null $extension Image extention (PNG or JPG)
     */
    public function save($destination = null, $extension = null)
    {
        $extension = $extension ?? $this->extension;

        if ($extension == static::PNG) {
            imagepng($this->resource, $destination ?? $this->file, 6);
        } else {
            imagejpeg($this->resource, $destination ?? $this->file);
        }
    }
}
