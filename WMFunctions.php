<?php
$cache = []; // 初始化缓存变量

function wpWaterMarkPosition($point, $imgWidth, $imgHeight, $textWidth, $textLength, $lineHeight, $margin) {
    $pointLeft = $margin;
    $pointTop = $margin;
    if ($point == 0) {
        $point = mt_rand(1, 9);
    }
    switch ($point) {
        case 1:
            $pointLeft = $margin;
            $pointTop = $margin + $lineHeight;
            break;
        case 2:
            $pointLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = $margin + $lineHeight;
            break;
        case 3:
            $pointLeft = $imgWidth - $textWidth - $margin;
            $pointTop = $margin + $lineHeight;
            break;
        case 4:
            $pointLeft = $margin;
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
            break;
        case 5:
            $pointLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
            break;
        case 6:
            $pointLeft = $imgWidth - $textWidth - $margin;
            $pointTop = floor(($imgHeight - $textLength * $lineHeight) / 2);
            break;
        case 7:
            $pointLeft = $margin;
            $pointTop = $imgHeight - $margin;
            break;
        case 8:
            $pointLeft = floor(($imgWidth - $textWidth) / 2);
            $pointTop = $imgHeight - $margin;
            break;
        case 9:
            $pointLeft = $imgWidth - $textWidth - $margin;
            $pointTop = $imgHeight - $margin;
            break;
    }
    return array('pointLeft' => $pointLeft, 'pointTop' => $pointTop);
}

function wpWaterMarkSetAngle($angle, $point, $position, $textWidth, $imgHeight) {
    if ($angle < 90) {
        $diffTop = ceil(sin(deg2rad($angle)) * $textWidth);

        if (in_array($point, array(1, 2, 3))) {
            $position['pointTop'] += $diffTop;
        } elseif (in_array($point, array(4, 5, 6))) {
            if ($textWidth > ceil($imgHeight / 2)) {
                $position['pointTop'] += ceil(($textWidth - $imgHeight / 2) / 2);
            }
        }
    } elseif ($angle > 270) {
        $diffTop = ceil(sin(deg2rad(360 - $angle)) * $textWidth);

        if (in_array($point, array(7, 8, 9))) {
            $position['pointTop'] -= $diffTop;
        } elseif (in_array($point, array(4, 5, 6))) {
            if ($textWidth > ceil($imgHeight / 2)) {
                $position['pointTop'] = ceil(($imgHeight - $diffTop) / 2);
            }
        }
    }
    return $position;
}

function wpwatermark_hex2rgb($hexColor) {
    $color = str_replace('#', '', $hexColor);
    if (strlen($color) > 3) {
        $rgb = array(
            'r' => hexdec(substr($color, 0, 2)),
            'g' => hexdec(substr($color, 2, 2)),
            'b' => hexdec(substr($color, 4, 2))
        );
    } else {
        $color = $hexColor;
        $r = substr($color, 0, 1) . substr($color, 0, 1);
        $g = substr($color, 1, 1) . substr($color, 1, 1);
        $b = substr($color, 2, 1) . substr($color, 2, 1);
        $rgb = array(
            'r' => hexdec($r),
            'g' => hexdec($g),
            'b' => hexdec($b)
        );
    }
    return $rgb['r'].','.$rgb['g'].','.$rgb['b'];
}

function wpWaterMarkCreateWordsWatermark($imgurl, $newimgurl, $text, $margin = 30, $fontSize = 14, $color = '#790000', $point = 1, $font = 'simhei.ttf', $angle = 0, $watermark_margin = 80, $output_webp = false) {
    global $cache; // 使用全局缓存变量

    $margin = intval($margin);
    $angle = intval($angle);
    $watermark_margin = intval($watermark_margin);

    $imageCreateFunArr = array(
        'image/jpeg' => 'imagecreatefromjpeg',
        'image/png'  => 'imagecreatefrompng',
        'image/gif'  => 'imagecreatefromgif'
    );
    $imageOutputFunArr = array(
        'image/jpeg' => 'imagejpeg',
        'image/png'  => 'imagepng',
        'image/gif'  => 'imagegif'
    );

    $imgsize = getimagesize($imgurl);
    if (empty($imgsize)) {
        return false;
    }

    $imgWidth = $imgsize[0];
    $imgHeight = $imgsize[1];
    $imgMime = $imgsize['mime'];

    if (!isset($imageCreateFunArr[$imgMime]) || !isset($imageOutputFunArr[$imgMime])) {
        return false;
    }

    $imageCreateFun = $imageCreateFunArr[$imgMime];
    $imageOutputFun = $imageOutputFunArr[$imgMime];

    $im = $imageCreateFun($imgurl);

    $color = explode(',', wpwatermark_hex2rgb($color));
    $text_color = imagecolorallocate($im, intval($color[0]), intval($color[1]), intval($color[2]));

    $point = ($point >= 0 && $point <= 10) ? intval($point) : 1;
    $fontSize = max(1, intval($fontSize));
    $angle = ($angle >= 0 && $angle < 90 || $angle > 270 && $angle < 360) ? $angle : 0;
    $fontUrl = plugin_dir_path(__FILE__) . 'fonts/' . ($font ? $font : 'alibaba.otf');

    $textArray = explode('|', $text);
    $textCount = count($textArray);

    $dimensions = getTextDimensions($textArray, $fontSize, $fontUrl, $angle, $cache);
    $textWidth = $dimensions['maxWidth'];
    $lineHeight = $dimensions['lineHeight'];
    $totalHeight = $dimensions['totalHeight'];

    if ($textWidth + 40 > $imgWidth || $totalHeight + 40 > $imgHeight) {
        imagedestroy($im);
        return false;
    }

    if ($point == 10) {
        $x_limit = $imgWidth - $margin;
        $y_limit = $imgHeight - $margin;

        for ($x = $margin; $x < $x_limit; $x += ($textWidth + $watermark_margin)) {
            for ($y = $margin; $y < $y_limit; $y += ($lineHeight * $textCount + $watermark_margin)) {
                foreach ($textArray as $key => $line) {
                    imagettftext($im, $fontSize, $angle, $x, $y + $key * $lineHeight, $text_color, $fontUrl, $line);
                }
            }
        }
    } else {
        // 单一位置水印
        $position = wpWaterMarkPosition($point, $imgWidth, $imgHeight, $textWidth, $textCount, $lineHeight, $margin);
        if ($angle != 0) {
            $position = wpWaterMarkSetAngle($angle, $point, $position, $textWidth, $imgHeight);
        }

        foreach ($textArray as $key => $line) {
            imagettftext($im, $fontSize, $angle, $position['pointLeft'], $position['pointTop'] + $key * $lineHeight, $text_color, $fontUrl, $line);
        }
    }

    // 保存 WebP 格式
    if ($output_webp) {
        $webp_File = preg_replace('/\.(jpg|jpeg|png)$/', '.webp', $newimgurl);
		$over_size = false;// ($imgWidth > 2560 || $imgHeight > 2560); // 转码不再限制尺寸
        if (!$over_size && imagewebp($im, $webp_File, 85)) {
            imagedestroy($im);
            return $webp_File;
        }
    }

    $imageOutputFun($im, $newimgurl);
    imagedestroy($im);
    return $newimgurl;
}

function wpWaterMarkImageWatermarkPosition($point, $imgWidth, $imgHeight, $stampWidth, $stampHeight, $margin) {
    $pointLeft = $margin;
    $pointTop = $margin;
    if ($point == 0) {
        $point = mt_rand(1, 9);
    }
    switch ($point) {
        case 1:
            $pointLeft = $margin;
            $pointTop = $margin;
            break;
        case 2:
            $pointLeft = floor(($imgWidth - $stampWidth) / 2);
            $pointTop = $margin;
            break;
        case 3:
            $pointLeft = $imgWidth - $stampWidth - $margin;
            $pointTop = $margin;
            break;
        case 4:
            $pointLeft = $margin;
            $pointTop = floor(($imgHeight - $stampHeight) / 2);
            break;
        case 5:
            $pointLeft = floor(($imgWidth - $stampWidth) / 2);
            $pointTop = floor(($imgHeight - $stampHeight) / 2);
            break;
        case 6:
            $pointLeft = $imgWidth - $stampWidth - $margin;
            $pointTop = floor(($imgHeight - $stampHeight) / 2);
            break;
        case 7:
            $pointLeft = $margin;
            $pointTop = $imgHeight - $stampHeight - $margin;
            break;
        case 8:
            $pointLeft = floor(($imgWidth - $stampWidth) / 2);
            $pointTop = $imgHeight - $stampHeight - $margin;
            break;
        case 9:
            $pointLeft = $imgWidth - $stampWidth - $margin;
            $pointTop = $imgHeight - $stampHeight - $margin;
            break;
    }
    return array('pointLeft' => $pointLeft, 'pointTop' => $pointTop);
}

function wpWaterMarkCreateImageWatermark($img_url, $stamp_url, $newimgurl, $point, $pct = '100', $margin = '30', $watermark_margin = '80', $output_webp = false) {
    $pct = intval($pct);
    $margin = intval($margin);
    $watermark_margin = intval($watermark_margin);

    $imageFunMap = [
        'image/jpeg' => ['create' => 'imagecreatefromjpeg', 'output' => 'imagejpeg'],
        'image/png'  => ['create' => 'imagecreatefrompng', 'output' => 'imagepng']
    ];

    $im_size = getimagesize($img_url);
    $stamp_size = getimagesize($stamp_url);
    if (empty($im_size) || empty($stamp_size)) {
        return false;
    }

    list($imWidth, $imHeight, $imType) = $im_size;
    list($stampWidth, $stampHeight, $stampType) = $stamp_size;

    if ($imWidth < $stampWidth || $imHeight < $stampHeight) {
        return false;
    }

    if (!isset($imageFunMap[$im_size['mime']]) || !isset($imageFunMap[$stamp_size['mime']])) {
        return false;
    }

    $imCreateFun = $imageFunMap[$im_size['mime']]['create'];
    $stampCreateFun = $imageFunMap[$stamp_size['mime']]['create'];
    $im = $imCreateFun($img_url);
    $stamp = $stampCreateFun($stamp_url);

    $position = ($point == 10)
        ? wpWaterMarkTileWatermark($im, $stamp, $imWidth, $imHeight, $stampWidth, $stampHeight, $margin, $watermark_margin, $pct)
        : wpWaterMarkSinglePosition($im, $stamp, $point, $imWidth, $imHeight, $stampWidth, $stampHeight, $margin, $pct);

    $imageOutputFun = $imageFunMap[$im_size['mime']]['output'];
    $imageOutputFun($im, $newimgurl, 100);

    // 如果需要转换为 WebP 格式
    if ($output_webp) {
        $webp_File = preg_replace('/\.(jpg|jpeg|png)$/', '.webp', $newimgurl);
		$over_size = false;// ($imgWidth > 2560 || $imgHeight > 2560); // 转码不再限制尺寸
        if (!$over_size && imagewebp($im, $webp_File, 85)) {
            imagedestroy($im);
            imagedestroy($stamp);
            return $webp_File;
        }
    }

    imagedestroy($im);
    imagedestroy($stamp);
    return $newimgurl;
}

function wpWaterMarkTileWatermark($im, $stamp, $imWidth, $imHeight, $stampWidth, $stampHeight, $margin, $watermark_margin, $pct) {
    $x_length = $imWidth - $margin;
    $y_length = $imHeight - $margin;
    for ($x = $margin; $x < $x_length; $x += ($stampWidth + $watermark_margin)) {
        for ($y = $margin; $y < $y_length; $y += ($stampHeight + $watermark_margin)) {
            imagecopymerge($im, $stamp, $x, $y, 0, 0, $stampWidth, $stampHeight, $pct);
        }
    }
}

function wpWaterMarkSinglePosition($im, $stamp, $point, $imWidth, $imHeight, $stampWidth, $stampHeight, $margin, $pct) {
    $position = wpWaterMarkImageWatermarkPosition($point, $imWidth, $imHeight, $stampWidth, $stampHeight, $margin);
    imagecopymerge($im, $stamp, $position['pointLeft'], $position['pointTop'], 0, 0, $stampWidth, $stampHeight, $pct);
}

function generate_new_image_url($file_path) {
    $file_parts = pathinfo($file_path);
    $file_prefix = preg_match('/^\d+-/', $file_parts['filename'], $matches) ? $matches[0] : '';
    $encrypted_string = base_convert(substr(md5($file_parts['filename']), 0, 8), 16, 36);

    $new_file_name = $file_prefix . $encrypted_string . '.' . $file_parts['extension'];

    return $file_parts['dirname'] . '/' . $new_file_name;
}

function getTextDimensions($textArray, $fontSize, $fontUrl, $angle = 0, &$cache = []) {
    $cacheKey = md5(json_encode([$textArray, $fontSize, $fontUrl, $angle]));

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $dimensions = [
        'maxWidth' => 0,
        'totalHeight' => 0,
        'lineHeight' => 0,
        'textCount' => count($textArray),
    ];

    $lineHeight = 0;
    foreach ($textArray as $text) {
        $textSize = imagettfbbox($fontSize, $angle, $fontUrl, $text);
        $width = abs($textSize[4] - $textSize[0]);
        $height = abs($textSize[5] - $textSize[1]);

        $dimensions['maxWidth'] = max($dimensions['maxWidth'], $width);
        $lineHeight = max($lineHeight, $height);
    }

    $dimensions['lineHeight'] = $lineHeight + 3;
    $dimensions['totalHeight'] = $dimensions['lineHeight'] * $dimensions['textCount'];

    $cache[$cacheKey] = $dimensions;

    return $dimensions;
}