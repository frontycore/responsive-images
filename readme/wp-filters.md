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

- **`fri_theme_aspect_img_tag`** Change HTML output of \<figure class="ratio"\>. Used in *getAspectImgTag()* and *aspectImgTag()* methods. Arguments:
	- `Html $figure` [Html](https://doc.nette.org/en/3.0/html-elements) figure object including image as it's child
	- `int $width` Width for ratio calculation.
	- `int $height` Height for ratio calculation.
	- `float $ratio` Calculated ratio in percents.


## UploadImage filters

- **`fri_upload_img_tag`** Change HTML output of non-responsive \<img\> tag. Used in *getImgTag()*, *imgTag()*. Arguments:
	- `Html $img` [Html](https://doc.nette.org/en/3.0/html-elements) image object
	- `ImageSize $size` ImageSize given as method's input

- **`fri_upload_responsive_img_tag`** Change output of responsive \<img\> tag. Used in *getResponsiveImgTag()* and *responsiveImgTag()* methods. Arguments:
	- `Html $img` [Html](https://doc.nette.org/en/3.0/html-elements) image object
	- `ImageSizeList $sizes` ImageSizeList given as method's input

- **`fri_upload_aspect_img_tag`** Change HTML output of \<figure class="ratio"\>. Used in *getAspectImgTag()* and *aspectImgTag()* methods. Arguments:
	- `Html $figure` [Html](https://doc.nette.org/en/3.0/html-elements) figure object including image as it's child
	- `int $width` Width for ratio calculation.
	- `int $height` Height for ratio calculation.
	- `float $ratio` Calculated ratio in percents.
	- `ImageSizeList|null $sizes` ImageSizeList for child responsive image.