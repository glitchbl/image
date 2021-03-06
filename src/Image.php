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
	protected $image_resource = null;

    /**
     * @var int Image width
     */
	protected $image_width;

    /**
     * @var int Image height
     */
	protected $image_height;

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
			
		list(, , $image_type) = getimagesize($file);

	    switch ($image_type) {
	        case IMAGETYPE_GIF:
	            $this->image_resource = imagecreatefromgif($file);
	            $this->extension = self::GIF;
	            break;
	        case IMAGETYPE_JPEG:
	            $this->image_resource = imagecreatefromjpeg($file);
	            $this->extension = self::JPG;
	            break;
	        case IMAGETYPE_PNG:
	            $this->image_resource = imagecreatefrompng($file);
	            $this->extension = self::PNG;
	            break;
		}
		
        $exif = @exif_read_data($file); // TEMP FIX
        $orientation = isset($exif['Orientation'])? $exif['Orientation']: 0;

        switch ($orientation) {
            case 2:
                imageflip($this->image_resource, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $this->image_resource = imagerotate($this->image_resource, 180, 0);
                break;
            case 4:
                imageflip($this->image_resource, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $this->image_resource = imagerotate($this->image_resource, -90, 0);
                imageflip($this->image_resource, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $this->image_resource = imagerotate($this->image_resource, -90, 0);
                break;
            case 7:
                $this->image_resource = imagerotate($this->image_resource, 90, 0);
                imageflip($this->image_resource, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $this->image_resource = imagerotate($this->image_resource, 90, 0); 
                break;
        }

	    $this->image_width = imagesx($this->image_resource);
	    $this->image_height = imagesy($this->image_resource);

	    $this->file = $file;
	}

    /**
     * @return string Image extension
     */
	public function getExtension()
	{
		return $this->extension;
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
     * @param int $max_width Max resized image width
     * @param int $max_height Max resized image height
	 * @return mixed Resource
     */
	protected function _resize($max_width = 1000, $max_height = 1000)
	{
	    $destination_width = $this->image_width;
	    $destination_height = $this->image_height;

	    if ($this->image_width / $this->image_height >= 1) {
	        if ($this->image_width > $max_width) {
	            $ratio = $max_width / $this->image_width;
	            $destination_width = $max_width;
	            $destination_height = (int)($this->image_height * $ratio);
	        }
            if ($destination_height > $max_height) {
	            $ratio = $max_height / $destination_height;
	            $destination_height = $max_height;
	            $destination_width = (int)($destination_width * $ratio);
            }
	    } else {
	        if ($this->image_height > $max_height) {
	            $ratio = $max_height / $this->image_height;
	            $destination_height = $max_height;
	            $destination_width = (int)($this->image_width * $ratio);
	        }
            if ($destination_width > $max_width) {
	            $ratio = $max_width / $destination_width;
	            $destination_width = $max_width;
	            $destination_height = (int)($destination_height * $ratio);
            }
	    }

	    $destination_resource = imagecreatetruecolor($destination_width, $destination_height);
	    $white = imagecolorallocate($destination_resource, 255, 255, 255);
	    imagefill($destination_resource, 0, 0, $white);
	    imagecopyresampled($destination_resource, $this->image_resource, 0, 0, 0, 0, $destination_width, $destination_height, $this->image_width, $this->image_height);

		return $destination_resource;
	}

    /**
     * @param string $destination Resized image location
     * @param string $extension Image extention (PNG or JPG)
     * @param int $max_width Max resized image width
     * @param int $max_height Max resized image height
     */
	public function resize($destination, $extension = self::PNG, $max_width = 1000, $max_height = 1000)
	{
		$destination_resource = $this->_resize($max_width, $max_height);
        $this->save($destination_resource, $extension, $destination);
	    imagedestroy($destination_resource);
	}

    /**
     * @param int $max_width Max resized image width
     * @param int $max_height Max resized image height
     */
	public function resizeSelf($max_width = 1000, $max_height = 1000)
	{
		$resource = $this->_resize($max_width, $max_height);
		$width = imagesx($resource);
		$height = imagesy($resource);
		imagedestroy($this->image_resource);
		$this->image_resource = $resource;
		$this->image_width = $width;
		$this->image_height = $height;
	}

    /**
     * @param int $type Filter type
     * @param void
     */
	public function filter($type)
	{
		imagefilter($this->image_resource, $type);
	}

    /**
     * @param string $destination Thumb location
     * @param int $thumb_width Thumb width
     * @param int $thumb_height Thumb height
     * @param string $extension Image extention (PNG or JPG)
     */
	public function thumb($destination, $thumb_width, $thumb_height, $extension = self::PNG)
	{
	    $cut_width = $this->image_width;
	    $cut_height = $this->image_height;

		$ratio_height_to_width = $thumb_width / $thumb_height;	    
		$ratio_width_to_height = $thumb_height / $thumb_width;	  

	    if ($cut_width <= $cut_height)
	    	$cut_height = (int)($cut_width * $ratio_width_to_height);
	    else
	    	$cut_width = (int)($cut_height * $ratio_height_to_width);

	    if ($cut_width > $this->image_width) {
	    	$ratio = $this->image_width / $cut_width;
	    	$cut_width = $this->image_width;
	    	$cut_height = (int)($ratio * $cut_height);
	    }

	    if ($cut_height > $this->image_height) {
	    	$ratio = $this->image_height / $cut_height;
	    	$cut_height = $this->image_height;
	    	$cut_width = (int)($ratio * $cut_width);
	    }

	    $start_x = round(($this->image_width / 2) - ($cut_width / 2));

	    $start_y = round(($this->image_height / 2) - ($cut_height / 2));

	    $destination_resource = imagecreatetruecolor($thumb_width, $thumb_height);
	    $white = imagecolorallocate($destination_resource, 255, 255, 255);
	    imagefill($destination_resource, 0, 0, $white);
	    imagecopyresampled($destination_resource, $this->image_resource, 0, 0, $start_x, $start_y, $thumb_width, $thumb_height, $cut_width, $cut_height);

        $this->save($destination_resource, $extension, $destination);
		
	    imagedestroy($destination_resource);
	}

    /**
     * @param mixed $resource Image resource
     * @param string $extension Image extention (PNG or JPG)
     * @param string $destination Image location
     */
    protected function save($resource, $extension, $destination)
    {
		if ($extension == 'png') {
	    	imagepng($resource, $destination, 6);
		} else {
			imagejpeg($resource, $destination);
		}
    }
}
