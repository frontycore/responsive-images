
# Suggested configuration

Following configuration helps you get the maximum from the library. To keep code clean, it is good idea to write the configuration to separate file, `setup-images.php` for example, which is included in `functions.php` **BELOW** the composer autoload:

```php
// functions.php
require_once(__DIR__ . '/setup-images.php');
```


## Uploaded image sizes

Since all uploaded images will be resized on the fly, **remove predefined Wordpress image sizes** to save space on your server. Leave only 192x192px thumbnail, which is used in WP admin Media Library.
Images uploaded by user will remain on your server in it's original size, [Wordpress resize these originals](https://developer.wordpress.org/reference/hooks/big_image_size_threshold/) to 2560px, but you can disable this behavior:

```php
// setup-images.php

// Remove default WP images sizes
add_action('after_switch_theme', function() {
	update_option('thumbnail_size_w', 192);
	update_option('thumbnail_size_h', 192);
	update_option('thumbnail_crop', 1);

	update_option('medium_size_w', 0);
	update_option('medium_size_h', 0);
	update_option('large_size_w', 0);
	update_option('large_size_h', 0);
});

// Disable extra image sizes for WP responsive srcsets
add_action('intermediate_image_sizes', function($sizes) {
	return array_diff($sizes, ['medium_large', '1536x1536', '2048x2048']);
});

// Remove WP size limit for images - don't resize the original images for future use
add_filter('big_image_size_threshold', '__return_false');
```


## Theme image paths

By default, theme images are loaded from the root theme directory, eg. `wp-content/themes/your-theme/image.jpg`. This is not the usual nor suggested usage. Use library filters to **addapt to your theme directory structure**:

```php
// setup-images.php

// Set theme images URL
add_filter('fri_theme_img_url', function(string $defaultUrl, string $file) {
	return get_template_directory_uri() . '/assets/images/' . $file;
}, 10, 2);

// Set theme image absolute path
add_filter('fri_theme_img_path', function(string $defaultPath, string $file) {
	return get_template_directory() . '/assets/images/' . $file;
}, 10, 2);
```


## Modify image HTML

Library offers few filters to change HTML output. They all accept [Html object](https://doc.nette.org/en/3.0/html-elements), which can be simply modified. This comes handy when you want to add lazyloading for example:

```php
// setup-images.php

use Nette\Utils\Html;

// Add lazyloading to theme images
add_filter('fri_theme_img_tag', 'addImgLazyloading');
// Add lazyloading to non-responsive uploaded images
add_filter('fri_upload_img_tag', 'addImgLazyloading');
// Add lazyloading to responsive uploaded images
add_filter('fri_upload_responsive_img_tag', 'addImgLazyloading');

function addImgLazyloading(Html $img)
{
	// Move src to data-src attribute
	$img->data('src', $img->src);
	$img->src = '';

	// Move srcset to data-srcset attribute
	if ($img->srcset) {
		$img->data('srcset', $img->srcset);
		$img->srcset = '';
	}

	// Add .lazyloading class
	$img->addClass('lazyloading');

	// For sake of conventions, not neccessary to return the object
	return $img;
}
```


## Replace Gutenberg image block with responsive image tag

Image block can be aligned to center, left, right, wide or full. It's alignement defines the width of image, so you need to specify image sizes for each possible alignement. Each template may have different block align widths, following is only an example. Take a look bellow at complete `ImageSizeList` class documentation.

```php
// setup-images.php

use Fronty\ResponsiveImages\Sizes\ImageSizeList;

// Replace <img> tag in Gutenberg core/image block with responsive image
add_filter('render_block', function(string $blockContent, array $block) {
	if ($block['blockName'] !== 'core/image') return $blockContent;

	// Get block alignement: center (default) | right | left | wide | full
	$align = (isset($block['attrs']['align'])) ? $block['attrs']['align'] : 'center';

	// Create ImageSizeList for every block align
	if ($align === 'center') {
		$sizes = ImageSizeList::fromArray([
			0 => [400, 250], // 400x250px cropped image, also a default if no media query match (mobileFirst mode)
			576 => [546, 300], // 576x300px cropped image for (min-width: 576px)
			768 => 738, // image width 738px, proportional height, no crop for (min-width: 768px)
			992 => 960 // image width 738px, proportional height, no crop for viewports wider or equal to 992px
		]);
	} else if ($align === 'right') {
		// ...specify for all other sizes
	}

	// Replace <img> tag in block with responsive image tag
	return img_upload($block['attrs']['id'])
		->replaceImageBlock($blockContent, $sizes);
}, 10, 2);
```

**Performance concern**: Gutenberg blocks are made in React, since this library is server-side, it uses [PHPHtmlParser](https://github.com/paquettg/php-html-parser) to parse HTML from string, which might be a little slow. Consider using page cache to suppress this issue.


## Factory functions

If you want to write as little code as possible in your templates, you can create factory functions, which will create and configure instances of useful classes for you:

```php
// setup-themes.php

use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\ThemeImage;
use Fronty\ResponsiveImages\Sizes\ImageSize;
use Fronty\ResponsiveImages\Sizes\ImageSizeList;
use Fronty\ResponsiveImages\Sizes\BootstrapSizes;

/**
 * Get uploaded image object by given WP attachment id.
 * @param int $ID
 * @return UploadImage
 */
function img_upload(int $ID): UploadImage
{
	return new UploadImage($ID);
}

/**
 * Get theme image object by given file path relative to theme directory (may be changed by fri_theme_img_url and fri_theme_img_path filters).
 * @param string $file Eg. 'icon.svg', 'image.jpg' etc.
 * @return ThemeImage
 */
function img_theme(string $file): ThemeImage
{
	return new ThemeImage($file);
}

/**
 * Create size for responsive image.
 * @param int $width
 * @param int $height
 * @param string|bool|null $crop
 * @param array $cloudinaryTransform
 * @return ImageSize
 *
 * @see ImageSize::__construct()
 */
function img_size(int $width, int $height = null, $crop = null, array $cloudinaryTransform = []): ImageSize
{
	return new ImageSize($width, $height, $crop, $cloudinaryTransform);
}

/**
 * Create ImageSizeList from given array.
 * @param array $imageSizes Viewport widths as keys, array of arguments for ImageSize::__construct() as values.
 * 	Example:
 * 	[
 * 		375 => [300, 250, 'fill', ['quality' => '80', 'gravity' => 'face']], // Full ImageSize arguments list
 * 		576 => 500, // Only first ImageSize argument $width (others will have default values)
 * 		...
 * 	]
 * @param bool $mobileFirst Whether to use breakpoints min-width (true) or max-width (false) media-query.
 * @return ImageSizeList
 *
 * @see ImageSizeList::fromArray()
 */
function img_sizes(array $imageSizes = [], bool $mobileFirst = true): ImageSizeList
{
	return ImageSizeList::fromArray($imageSizes, $mobileFirst);
}

/** For Bootstrap or similar grid-system users only */

/**
 * Create sizes generator for Twitter Bootstrap grid-system.
 * @return BootstrapSizes
 */
function bootstrap_sizes()
{
	return new BootstrapSizes();
}
```