# RESPONSIVE IMAGES FOR WORDPRESS

Library that will make working with images in your responsive Wordpress template a breeze.

**What's in the box:**
- Generates \<img\> tags with precise `srcset` and `sizes` attributes for images from WP Media Library.
- Generates \<img\> tags wrapped in aspect-ratio preserving wrapper for both WP Media images and theme images. Great to prevent CLS with lazyloaded images.
- Clean API to define responsive image sizes in either mobile-first (min-width) or desktop-first (max-width) manner.
- Helper to define images sizes based on taken colums in Bootstrap grid for each breakpoint.
- Replace responsive image in Gutenberg image block.
- Generates sanitized and minified \<svg\> code, which can be safely inlined to HTML.


**Code**
```php
<?php
$sizes = img_sizes([
	0 => [400, 250], // 400x250px cropped image for viewport 0 - 575px
	576 => 500, // 500px wide, proportional height image for viewpoer 576 - 767px
	768 => 700, // ...
	992 => 900,
	1200 => 1100
]);
img_upload($attachment_id)->responsiveImgTag($sizes, ['class' => 'img-fluid', 'alt' => 'My image']);
```

**Output**
```html
<img
	src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1100,c_fit/your-project/2021/05/your-image.jpg"
	sizes="(min-width: 1200px) 1100px,
		(min-width: 992px) 900px,
		(min-width: 768px) 700px,
		(min-width: 576px) 500px,
		400px"
	srcset="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_1100,c_fit/your-project/2021/05/your-image.jpg 1100w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_900,c_fit/your-project/2021/05/your-image.jpg 900w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_700,c_fit/your-project/2021/05/your-image.jpg 700w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_500,c_fit/your-project/2021/05/your-image.jpg 500w,
		https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_250,c_fill/your-project/2021/05/your-image.jpg 400w"
	width="1100" height="619"
	alt="My image"
	class="img-fluid">
```

> **Thanks a lot to [Adam Laita](https://github.com/adam-laita) and [his post](https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/) about image optimization (in Czech only). It was the original inspiration for this library.**

# Instalation

Install the library to your theme using Composer:

```
composer require fronty/responsive-images
```

Include composer's autoload in `functions.php`:

```php
// functions.php
require_once(__DIR__ . '/vendor/autoload.php');
```


## WP Plugins

Install **[Fly Dynamic Image Resizer](https://cs.wordpress.org/plugins/fly-dynamic-image-resizer/)** and activate it on local development version. You can use this on production as well, but you would miss the speed of CDN. On production version install, activate and configure **[Auto Cloudinary](https://wordpress.org/plugins/auto-cloudinary/)**, which will automatically upload all your images to [Cloudinary](https://cloudinary.com/) using it's fetch API.

This library decides whether to use *Fly Dynamic Image Resizer* or *Auto Cloudinary* plugin, but make sure **only one of these plugins is activated at a time**. If none of these plugins is activated, library will fall back to default Wordpress attachment getter `wp_get_attachment_image_src()` with size given as array.

The library can work with SVGs as well. To support SVGs in WP Media Library, install **[SVG Support](https://wordpress.org/plugins/svg-support/)**.


> **With this, you are ready to instantiate all classes described below directly in your template files.** However to optimize your images and code as much as possible, there are [more steps suggested](./readme/configuration.md).


# Working with image sizes

To work with responsive images, we first has to prepare one or more image sizes for various breakpoints. Image sizes can be defined using following classes:
- [`Fronty\ResponsiveImages\Sizes\ImageSize`](./readme/image-sizes.md#imagesize-object) is representation of image dimensions and transformations. This class alone is used for non-responsive images, where only one size is required.
- [`Fronty\ResponsiveImages\Sizes\ImageSizeList`](./readme/image-sizes.md#imagesizelist-object) is representation of group of ImageSize objects and is used for responsive images, where different size is required for each breakpoint.
- [`Fronty\ResponsiveImages\Sizes\BootstrapSizes`](./readme/image-sizes.md#bootstrapsizes-object) is helper class to generate ImageSizeList according to specified container width and grid columns.

See [detailed documentation of these objects](./readme/image-sizes.md).


# Working with images

Library includes two main classes for work with images:
- [`Fronty\ResponsiveImages\UploadImage`](./readme/images.md#uploadimage-object) for images uploaded by user in WP Media Gallery
- [`Fronty\ResponsiveImages\ThemeImage`](./readme/images.md#themeimage-object) for images located inside your theme directory (logos, backgrounds, etc.)

See [detailed documentation of these objects](./readme/images.md).


# WP filters

Both ThemeImage and UploadImage objects allows you to change some of their outputs using [WP filter mechanism](https://developer.wordpress.org/plugins/hooks/filters/).

See [list of all filters available](./readme/wp-filters.md).


# Dependencies

### Nette\Utils
The code depends on lightweight utility classes from [Nette\Utils](https://github.com/nette/utils), especially following classes:
- [HTML elements](https://doc.nette.org/en/3.1/html-elements) for img HTML tags handling
- [Strings](https://doc.nette.org/en/3.1/strings) for safe UTF-8 strings manipulation
- [ArratHash](https://doc.nette.org/en/3.1/arrays#toc-arrayhash) for smart array-like object


### Other dependencies
- [enshrined/svg-sanitize](https://github.com/darylldoyle/svg-sanitizer): Remove ids and classes of uploded SVGs before they are inlined to template.
- [paquettg/php-html-parser](https://github.com/paquettg/php-html-parser) Replace responsive image tag in Gutenberg image block.
