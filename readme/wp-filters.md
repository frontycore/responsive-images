# WP filters

Both ThemeImage and UploadImage objects allows you to change some of their outputs using [WP filter mechanism](https://developer.wordpress.org/plugins/hooks/filters/).

## ThemeImage filters

- **`fri_theme_img_url`** Change theme file URL. Arguments:
	- `string $url` Default image URL in theme root, eg. 'http://domain.com/wp-content/themes/your-theme/image.jpg'
	- `string $file` File name with extension, eg. 'image.jpg'

- **`fri_theme_img_path`** Change theme file path. Arguments:
	- `string $path` Default image path in theme root, eg. '/Users/fronty/www/domain.com/themes/your-theme/image.jpg'
	- `string $file` File name with extension, eg. 'image.jpg'

- **`fri_theme_img_tag`** Change HTML output of \<img\> tag. Used in *getImgTag()* and *imgTag()*. Arguments:
	- `Html $img` [Html](https://doc.nette.org/en/3.0/html-elements) image object
	- `ImageSize $size` ImageSize given as method's input
	- `ThemeImage $image` The image object itself

- **`fri_theme_aspect_img_tag`** Change HTML output of \<figure class="ratio"\>. Used in *getAspectImgTag()* and *aspectImgTag()* methods. Arguments:
	- `Html $figure` [Html](https://doc.nette.org/en/3.0/html-elements) figure object including image as it's child
	- `int $width` Width for ratio calculation.
	- `int $height` Height for ratio calculation.
	- `float $ratio` Calculated ratio in percents.
	- `ThemeImage $image` The image object itself


## UploadImage filters

- **`fri_upload_img_tag`** Change HTML output of non-responsive \<img\> tag. Used in *getImgTag()*, *imgTag()*. Arguments:
	- `Html $img` [Html](https://doc.nette.org/en/3.0/html-elements) image object
	- `ImageSize $size` ImageSize given as method's input
	- `UploadImage $image` The image object itself

- **`fri_upload_responsive_img_sizes`** Change parts of the `sizes` attribute before they are joined by `, `. Used in *getResponsiveImgTag()* and *responsiveImgTag()* methods. Arguments:
	- `array $sizes` Declared slot widths with their media conditions in the order they are written to the attribute, eg. `['(min-width: 1200px) 1100px', …, '400px']`
	- `UploadImage $image` The image object itself

- **`fri_upload_responsive_img_srcset`** Change `srcset` candidates before they are joined by `, `. Used in *getResponsiveImgTag()* and *responsiveImgTag()* methods. Arguments:
	- `array $srcsets` Candidates with their `w` descriptors, deduplicated and sorted by width from the smallest one, eg. `['….jpg 400w', '….jpg 800w', …]`. Contains every declared width in all [pixel densities](./image-sizes.md#pixel-densities) of the ImageSizeList.
	- `UploadImage $image` The image object itself

- **`fri_upload_responsive_img_tag`** Change output of responsive \<img\> tag. Used in *getResponsiveImgTag()* and *responsiveImgTag()* methods. Arguments:
	- `Html $img` [Html](https://doc.nette.org/en/3.0/html-elements) image object
	- `ImageSizeList $sizes` ImageSizeList given as method's input
	- `UploadImage $image` The image object itself

- **`fri_upload_format_src`** Confirm the URL of the image file in an alternative format, eg. AVIF or WebP. Used in *getResponsivePictureTag()*, *responsivePictureTag()*, *getAspectPictureTag()*, *aspectPictureTag()* and in *replaceImageBlock()* with the $withPicture argument. **This library converts nothing**, it only offers the files you confirm here, so without this filter no \<source\> is rendered at all. Return the URL of the file in the required format, or `null` when there is none - a format is offered only when all candidates of the `srcset` are confirmed in it. See [ready-made filters for the usual converter layouts](./images.md#where-do-the-converted-files-come-from). Arguments:
	- `null $src` Always null, so an unhooked filter renders no source.
	- `string $url` URL of the candidate in the original format, eg. 'https://domain.com/wp-content/uploads/bis-images/2026/10/image-1100x688-c.jpg'
	- `string|null $path` Server path of the candidate, null when it doesn't point into the uploads directory, eg. to a CDN. Handy to check the file with `file_exists()`.
	- `string $format` Required image MIME type, eg. 'image/avif'
	- `UploadImage $image` The image object itself

- **`fri_upload_picture_sources`** Change the \<source\> elements before they are added to the \<picture\> tag. Used in the same methods as `fri_upload_format_src`. Return the array. Arguments:
	- `array $sources` [Html](https://doc.nette.org/en/3.0/html-elements) source objects with `type`, `srcset` and `sizes` attributes, in the order of the formats. Empty array when no format is confirmed.
	- `ImageSizeList $sizes` ImageSizeList given as method's input
	- `UploadImage $image` The image object itself

	> **This is the hook a lazyloader needs.** Inside \<picture\> the `srcset` of a \<source\> wins over the \<img\>, so a lazyloader which rewrites the \<img\> attributes only is silently bypassed and the browser loads every source immediately.

- **`fri_upload_picture_tag`** Change output of \<picture\> tag. Used in the same methods as `fri_upload_format_src`. Arguments:
	- `Html $picture` [Html](https://doc.nette.org/en/3.0/html-elements) picture object including it's sources and image
	- `ImageSizeList $sizes` ImageSizeList given as method's input
	- `UploadImage $image` The image object itself

- **`fri_upload_aspect_img_tag`** Change HTML output of \<figure class="ratio"\>. Used in *getAspectImgTag()* and *aspectImgTag()* methods. Arguments:
	- `Html $figure` [Html](https://doc.nette.org/en/3.0/html-elements) figure object including image as it's child
	- `int $width` Width for ratio calculation.
	- `int $height` Height for ratio calculation.
	- `float $ratio` Calculated ratio in percents.
	- `ImageSizeList|null $sizes` ImageSizeList for child responsive image.
	- `UploadImage $image` The image object itself


## Shared action

- **`fri_image_issue`** Fires when an image problem would otherwise leave the output silently empty - an unreadable SVG file or an SVG rejected by the sanitizer. Hook it to report the problem elsewhere, eg. to a monitoring service. The message is triggered as `E_USER_WARNING` when `WP_DEBUG` is on and passed to `error_log()` otherwise, so it is never lost. Arguments:
	- `string $message` Reason of the issue including the class name and the file path or URL.
	- `BaseImage $image` The image object, which reported the issue.