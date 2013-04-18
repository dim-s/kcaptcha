<?php
namespace kcaptcha;

/**
 * KCaptcha 3.0 Fork for PHP 5.3+
 * By Dmitriy Zayceff (dz@dim-s.net) 2013
 * Class KCaptcha
 */

/* KCAPTCHA PROJECT VERSION 2.0
    # Automatic test to tell computers and humans apart

    # Copyright by Kruglov Sergei, 2006, 2007, 2008, 2011
    # www.captcha.ru, www.kruglov.ru

    # System requirements: PHP 4.0.6+ w/ GD

    # KCAPTCHA is a free software. You can freely use it for developing own site or software.
    # If you use this software as a part of own sofware, you must leave copyright notices intact or add KCAPTCHA copyright notices to own.
    # As a default configuration, KCAPTCHA has a small credits text at bottom of CAPTCHA image.
    # You can remove it, but I would be pleased if you left it. ;)

    # See kcaptcha_config.php for customization
*/

class KCaptcha {

    private $config;
    private $fonts;

    private $keyString;

	public function __construct($config = array()){
        $defaults = array(
            'alphabet' => '0123456789abcdefghijklmnopqrstuvwxyz',
            'allowed_symbols' => '23456789abcdegikpqsvxyz',
            'fonts_dir' => 'fonts',
            'length' => array(5, 7),
            'width' => 160,
            'height' => 80,
            'fluctuation_amplitude' => 8,

            'white_noise_density' => 1/6,
            'black_noise_density' => 1/30,
            'no_spaces' => true,

            'foreground_color' => array(array(0, 80), array(0, 80), array(0, 80)),
            'background_color' => array(array(220, 255), array(220, 255), array(220, 255)),

            'jpeg_quality' => 90
        );

        $this->config = ($config = array_merge($defaults, $config));

		$fonts = array();
		$fonts_dir_absolute = dirname(__FILE__) . '/' . $config['fonts_dir'];
		if ($handle = opendir($fonts_dir_absolute)) {
			while (false !== ($file = readdir($handle))) {
				if (preg_match('/\.png$/i', $file)) {
					$fonts[]=$fonts_dir_absolute . '/' . $file;
				}
			}
		    closedir($handle);
        }
        $this->fonts = $fonts;

        $length = is_array($config['length']) ? mt_rand($config['length'][0], $config['length'][1]) : (int)$config['length'];
        if (!$length) $length = 5;

        // generating random keystring
        while(true){
            $this->keyString = '';
            for($i = 0; $i < $length; $i++){
                $this->keyString .= $config['allowed_symbols'][mt_rand(0,strlen($config['allowed_symbols'])-1)];
            }
            if(!preg_match('/cp|cb|ck|c6|c9|rn|rm|mm|co|do|cl|db|qp|qb|dp|ww/', $this->keyString)) break;
        }
	}

    /**
     * render captcha image
     * @return resource image
     */
    public function render(){
        $config = $this->config;
        $alphabet_length = strlen($config['alphabet']);
        $length = strlen($this->keyString);

        do {
            $font_file = $this->fonts[mt_rand(0, count($this->fonts)-1)];
            $font = imagecreatefrompng($font_file);
            imagealphablending($font, true);

            $fontfile_width  = imagesx($font);
            $fontfile_height = imagesy($font)-1;

            $font_metrics = array();
            $symbol = 0;
            $reading_symbol = false;

            // loading font
            $alphabet = $config['alphabet'];
            for($i=0; $i < $fontfile_width && $symbol < $alphabet_length; $i++){
                $transparent = (imagecolorat($font, $i, 0) >> 24) == 127;

                if(!$reading_symbol && !$transparent){
                    $font_metrics[$alphabet[$symbol]] = array('start' => $i);
                    $reading_symbol = true;
                    continue;
                }

                if($reading_symbol && $transparent){
                    $font_metrics[$alphabet[$symbol]]['end'] = $i;
                    $reading_symbol = false;
                    $symbol++;
                    continue;
                }
            }

            $img = imagecreatetruecolor($config['width'], $config['height']);
            imagealphablending($img, true);
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);

            imagefilledrectangle($img, 0, 0, $config['width']-1, $config['height']-1, $white);

            // draw text
            $x = 1;
            $odd = mt_rand(0, 1);
            if($odd == 0) $odd=-1;
            for($i=0;$i < $length;$i++){
                $m = $font_metrics[$this->keyString[$i]];

                $y= (($i%2)*$config['fluctuation_amplitude'] - $config['fluctuation_amplitude']/2)*$odd
                    + mt_rand(-round($config['fluctuation_amplitude']/3), round($config['fluctuation_amplitude']/3))
                    + ($config['height']-$fontfile_height)/2;

                if($config['no_spaces']){
                    $shift=0;
                    if($i > 0){
                        $shift = 10000;
                        for($sy = 3; $sy < $fontfile_height-10; $sy += 1){
                            for($sx = $m['start'] - 1; $sx < $m['end']; $sx += 1){
                                $rgb = imagecolorat($font, $sx, $sy);
                                $opacity = $rgb>>24;
                                if($opacity < 127){
                                    $left = $sx - $m['start'] + $x;
                                    $py = $sy + $y;
                                    if($py > $config['height'])
                                        break;

                                    for($px = min($left, $config['width'] - 1); $px > $left-200 && $px >= 0; $px -= 1){
                                        $color = imagecolorat($img, $px, $py) & 0xff;
                                        if($color + $opacity < 170){ // 170 - threshold
                                            if($shift > $left - $px){
                                                $shift = $left-$px;
                                            }
                                            break;
                                        }
                                    }
                                    break;
                                }
                            }
                        }
                        if($shift == 10000){
                            $shift = mt_rand(4,6);
                        }
                    }
                } else {
                    $shift = 1;
                }
                imagecopy($img, $font, $x - $shift, $y, $m['start'], 1, $m['end'] - $m['start'], $fontfile_height);
                $x += $m['end'] - $m['start'] - $shift;
            }
        } while($x >= $config['width'] - 10); // while not fit in canvas


        //noise
        $white = imagecolorallocate($font, 255, 255, 255);
        $black = imagecolorallocate($font, 0, 0, 0);
        for($i = 0; $i < (($config['height'] - 30) * $x) * $config['white_noise_density']; $i++){
            imagesetpixel($img, mt_rand(0, $x-1), mt_rand(10, $config['height'] - 15), $white);
        }
        for($i = 0;$i<(($config['height'] - 30) * $x) * $config['black_noise_density']; $i++){
            imagesetpixel($img, mt_rand(0, $x-1), mt_rand(10, $config['height'] - 15), $black);
        }

        $center = $x / 2;
        $foreground_color = self::getRandomRGB($config['foreground_color']);
        $background_color = self::getRandomRGB($config['background_color']);

        // periods
        $rand1 = mt_rand(750000,1200000)/10000000;
        $rand2 = mt_rand(750000,1200000)/10000000;
        $rand3 = mt_rand(750000,1200000)/10000000;
        $rand4 = mt_rand(750000,1200000)/10000000;

        // phases
        $rand5 = mt_rand(0,31415926)/10000000;
        $rand6 = mt_rand(0,31415926)/10000000;
        $rand7 = mt_rand(0,31415926)/10000000;
        $rand8 = mt_rand(0,31415926)/10000000;

        // amplitudes
        $rand9 = mt_rand(330,420)/110;
        $rand10 = mt_rand(330,450)/100;

        // create image
        $img2 = imagecreatetruecolor($config['width'], $config['height']);
        $foreground = imagecolorallocate($img2, $foreground_color[0], $foreground_color[1], $foreground_color[2]);
        $background = imagecolorallocate($img2, $background_color[0], $background_color[1], $background_color[2]);
        imagefilledrectangle($img2, 0, 0, $config['width'] - 1, $config['height']-1, $background);
        imagefilledrectangle($img2, 0, $config['height'], $config['width'] - 1, $config['height'], $foreground);

        //wave distortion
        for($x=0; $x < $config['width']; $x++){
            for($y=0;$y < $config['height']; $y++){
                $sx = $x + (sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6)) * $rand9 - $config['width'] / 2 + $center + 1;
                $sy = $y + (sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8)) * $rand10;

                if($sx < 0 || $sy < 0 || $sx >= $config['width'] - 1 || $sy >= $config['height'] - 1){
                    continue;
                } else {
                    $color    = imagecolorat($img, $sx, $sy) & 0xFF;
                    $color_x  = imagecolorat($img, $sx + 1, $sy) & 0xFF;
                    $color_y  = imagecolorat($img, $sx, $sy + 1) & 0xFF;
                    $color_xy = imagecolorat($img, $sx + 1, $sy + 1) & 0xFF;
                }

                if($color == 255 && $color_x == 255 && $color_y == 255 && $color_xy == 255){
                    continue;
                } elseif($color == 0 && $color_x == 0 && $color_y == 0 && $color_xy == 0){
                    $newred   = $foreground_color[0];
                    $newgreen = $foreground_color[1];
                    $newblue  = $foreground_color[2];
                } else {
                    $frsx = $sx - floor($sx);
                    $frsy = $sy - floor($sy);
                    $frsx1 = 1 - $frsx;
                    $frsy1 = 1 - $frsy;

                    $newcolor = (
                        $color * $frsx1 * $frsy1+
                            $color_x * $frsx * $frsy1+
                            $color_y * $frsx1 * $frsy+
                            $color_xy * $frsx * $frsy);

                    if($newcolor > 255){
                        $newcolor = 255;
                    }
                    $newcolor  = $newcolor/255;
                    $newcolor0 = 1 - $newcolor;

                    $newred   = $newcolor0 * $foreground_color[0] + $newcolor * $background_color[0];
                    $newgreen = $newcolor0 * $foreground_color[1] + $newcolor * $background_color[1];
                    $newblue  = $newcolor0 * $foreground_color[2] + $newcolor * $background_color[2];
                }

                imagesetpixel($img2, $x, $y, imagecolorallocate($img2, $newred, $newgreen, $newblue));
            }
        }
        return $img2;
    }

    /**
     * get captcha input word
     * @return string
     */
    function getKeyString(){
		return $this->keyString;
	}

    /**
     * @param array $options
     * @return array
     */
    private static function getRandomRGB(array $options){
        $result = array(0, 0, 0);
        $result[0] = mt_rand($options[0][0], $options[0][1]);
        $result[1] = mt_rand($options[1][0], $options[1][1]);
        $result[2] = mt_rand($options[2][0], $options[2][1]);
        return $result;
    }
}