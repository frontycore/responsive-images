# Working with images

Library includes two main classes for work with images:
- [`Fronty\ResponsiveImages\UploadImage`](#uploadimage-object) for images uploaded by user in WP Media Gallery
- [`Fronty\ResponsiveImages\ThemeImage`](#themeimage-object) for images located inside your theme directory (logos, backgrounds, etc.)

Methods of both classes, which generates HTML output are always available in two variants:
- with `get` prefix returns [Html](https://doc.nette.org/en/3.0/html-elements) object for futher modification
- without `get` prefix outputs HTML directly

> All methods generating \<img\> tag sets `width` and `height` attributes properly from ImageSize, or from widest size found in ImageSizeList in case of responsive images. If height is not given explicitly, it will be calculated proprtionaly.

---

## Shared methods
Both UploadImage and ThemeImage class extends mutual abstract parent class `Fronty\ResponsiveImages\BaseImage`, which contains or forces following shared methods:

### `isSvg()`

Check if image is SVG.

- `@return bool`

### `getPathinfo()`

Returns image [pathinfo](https://www.php.net/manual/en/function.pathinfo.php) as the ArrayHash.

- `@return ArrayHash` [ArrayHash](https://doc.nette.org/en/3.0/arrays#toc-arrayhash) object with pathinfo data.

### `getUrl()` and `getPath()`
Returns fully qualified URL or absolute path of image original size. In context of `UploadImage`, this always returns url or path on your server (not from Cloudinary).

> These methos in ThemeImage object uses `fri_theme_img_url` and `fri_theme_img_path` filters, see [more about WP filters](./wp-filters.md).

- `@return string` Fully qualified URL or path of the image on local server.

### `getImgTag()` and `imgTag()`

Returns or output non-responsive \<img\> HTML tag. Useful also for SVG in \<img\> tags.

- `@param ImageSize $size` How to resize image. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@param array $attrs = []` Optional image tag HTML attributes.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<img\>.

> Uses `fri_upload_img_tag` with UploadImage and `fri_theme_img_tag` in ThemeImage, see [more about WP filters](./wp-filters.md).

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSize;

(new UploadImage($attachment_id))->imgTag(new ImageSize(400, 200), ['alt' => 'My uploaded image']);

(new ThemeImage('my-image.jpg'))->imgTag(new ImageSize(400, 200), ['alt' => 'My theme image']);

// With factory function
img_upload($attachment_id)->imgTag(img_size(400, 200), ['alt' => 'My uploaded image']);
img_theme('my-image.jpg')->imgTag(img_size(400, 200), ['alt' => 'My theme image']);
```

Output:
```html
<img
	src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_200,c_fill/your-project/2021/05/your-image.jpg"
	width="400" height="200"
	alt="My uploaded image">

<img
	src="https://your-project.com/wp-content/themes/your-theme/my-image.jpg"
	width="400" height="200"
	alt="My theme image">
```

### `getInlineSvg()` and `inlineSvg()`

Return or ouput inline \<svg\>. All classes in SVG are changed to unique using random hash. In UploadTheme, the SVG is also sanitized using [darylldoyle/svg-sanitizer](https://github.com/darylldoyle/svg-sanitizer).

- `@return string` SVG code.
- `@throws LogicException` If the image is not of SVG type. Check image using `isSvg()` before.

---

## UploadImage object

> All methods generating \<img\> tag takes care of **`alt`** attribute. If you don't specify it explicitly in $attrs argument, it will be set automatically from "Alternative text" or "Title" field in WP Media Library.

### `__construct()`

- `@param int $attachment_id` ID of WP attachment post.

Example:
```php
use Fronty\ResponsiveImages\UploadImage;

$img = new UploadImage($attachment_id);

// Or using suggested factory function:
$img = img_upload($attachment_id);
```

### `isSvg()`, `getPathinfo()`, `getUrl()`, `getPath()`, `getInlineSvg()`, `inlineSvg()`, `getImgTag()` and `imgTag()`

These methods is documented in [Shared methods above](#shared-methods).

### `getSizedSrc()`

Returns fully qualified URL, width and height of an image in required size. Returns URL from Cloudinary if Auto Cloudinary plugin is active, otherwise returns scaled image from local server.

- `@param ImageSize $size` Size to which to scale image. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@return array` [ 0 => string URL, 1 => int width, 2 => int height ]

### `getImgLink()`

Get [Html object](https://doc.nette.org/en/3.0/html-elements) of \<a\> tag, which href points to image scaled by given ImageSize. Useful when creating lightbox links.

- `@param array $attrs = []` Optional HTML attributes for \<a\> tag.
- `@param ImageSize $size = null` Size to which scale the linked image. Unscaled image will be returned if null given. See [ImageSize docs](./image-sizes.md#imagesize-object).
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<a\> link without any children.

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSize;

$img = new UploadImage($attachment_id);

// Add any Html object inside the link object
echo $img->getImgLink(['class' => 'lightbox'], new ImageSize(1920))
	->addHtml(
		$img->getImgTag(new ImageSize(400, 250))
	);

// Same with suggested factory functions
$img = img_upload($attachment_id);
echo $img->getImgLink(['class' => 'lightbox'], img_size(1920))
	->addHtml($img->getImgTag(img_size(400, 250)));
```

### `getResponsiveImgTag()` and `responsiveImgTag()`

Get or output responsive \<img\> tag with `sizes` and `srcset` arguments calculated by given ImageSizeList.

Attributes `width` and `height` will be set according to the widest image scale found in ImageSizeList.

Attribute `alt` will be automatically filled with first non-empty field alt or title set in WP Media Library.

- `@param ImageSizeList $sizes` Sizes of the responsive image. See [ImageSizeList docs](./image-sizes.md#imagesizelist-object).
- `@param array $attrs = []` HTML attributes of the image, eg. ['class' => 'img-fluid', 'alt' => 'My image']
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<img\>.

> Uses `fri_upload_responsive_img_tag` filter, see [more about WP filters](./wp-filters.md).

Example:
```php
use Fronty\ResponsiveImages\UploadImage;
use Fronty\ResponsiveImages\Sizes\ImageSizeList;

$sizes = ImageSizeList::fromArray([
	0 => [400, 250],
	576 => 500,
	768 => 700,
	992 => 900,
	1200 => 1100
]);

(new UploadImage($attachment_id))
	->responsiveImgTag($sizes, [
		'class' => 'img-fluid',
		'alt' => 'My image'
	]);


// The same with suggested factory function
img_upload($attachment_id)
	->responsiveImgTag(
		img_sizes([
			0 => [400, 250],
			576 => 500,
			768 => 700,
			992 => 900,
			1200 => 1100
		]),
		[
			'class' => 'img-fluid',
			'alt' => 'My image'
		]
	);
```

Output:
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

### `getAspectImgTag()` and `aspectImgTag()`

Generate or outputs \<img\> tag wrapped in \<figure\> tag, which holds the image aspect-ratio before the image is loaded. It is useful with lazyloaded images to prevent [Cumulative Layout Shift](https://web.dev/cls/). Modern browsers starts to implement native ratio preserving images, so it might be redundant in the future.

- `@param int $width` Width for ratio calculation.
- `@param int $height` Height for ratio calculation.
- `@param ImageSizeList $sizes = null` Generate responsive image tag using `getResponsiveImgTag()` if given. If null, create non-responsive image using `getImgTag()` with size of given $width and $height.
- `@param array $wrapAttrs = []` HTML attributes for wrapping \<figure\> tag.
- `@param array $imgAttrs = []` HTML attributes for \<img\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<figure\> with \<img\> child.

> Uses `fri_upload_aspect_img_tag` filter, see [more about WP filters](./wp-filters.md).

Method calculates the ratio from mandatory $width and $height arguments and uses [Bootstrap 5 Ratio helper](https://getbootstrap.com/docs/5.0/helpers/ratio/#custom-ratios).

If you use this method with ImageSizeList object, where you specify different ratio for each breakpoint, you need to change the `--bs-aspect-ratio` CSS variable in your CSS for all breakpoints with different ratio. The value of `--bs-aspect-ratio` should be given in percent, for example with 16:9 ratio: 9 / 16 * 100% = 56.25%.

If you don't use Bootstrap, add following CSS to your project:

```css
.ratio {
	position: relative;
	width: 100%;
}
.ratio::before {
	display: block;
    padding-top: var(--bs-aspect-ratio);
    content: "";
}
.ratio img {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
}
```

Example:
```php
use Fronty\ResponsiveImages\UploadImage;

(new UploadImage($attachment_id))
	->aspectImgTag(
		400, 250, null,
		['class' => 'my-figure'], ['class' => 'lazyloaded', 'alt' => 'My image']
	);

// Or the same with suggested factory function
img_upload($attachment_id)
	->aspectImgTag(
		400, 250, null,
		['class' => 'my-figure'], ['class' => 'lazyloaded', 'alt' => 'My image']
	);
```

Output:
```html
<figure class="my-figure ratio" style="--bs-aspect-ratio:62.5%">
	<img
		src="https://res.cloudinary.com/your-name/q_auto:eco,f_auto,w_400,h_250,c_fill/your-project/2021/05/your-image.jpg"
		width="400" height="250"
		alt="My image"
		class="lazyloaded">
</figure>
```


---

## ThemeImage object

ThemeImage has similar methods as UploadImage, but it suppose you the images are already optimized by som task manager, so there is no sanitization with SVGs. Theme images can't be currently used with responsive attributes.

### `__construct()`

For `ThemeImage` to instantiate, you need relative location of the image within your theme directory. You can tweak source location of theme images using [wp filters](./wp-filters.md).

- `@param string $file`

Example:
```php
use Fronty\ResponsiveImages\ThemeImage;

$img = new ThemeImage('images/image.jpg');

// Or using suggested factory function
$img = img_theme('images/logo.svg');
```

### `isSvg()`, `getPathinfo()`, `getUrl()`, `getPath()`, `getInlineSvg()`, `inlineSvg()`, `getImgTag()` and `imgTag()`

These methods is documented in [Shared methods above](#shared-methods).

### `getAspectImgTag()` and `aspectImgTag()`

Unlike UploadImage, these methods can't be used with responsive attributes, so ImageSizeList argument is omitted. Besides this fact, these methods work exactly the same as the ones in [UploadImage object](#uploadimage-object).

- `@param int $width` Width for ratio calculation.
- `@param int $height` Height for ratio calculation.
- `@param array $wrapAttrs = []` HTML attributes for wrapping \<figure\> tag.
- `@param array $imgAttrs = []` HTML attributes for \<img\> tag.
- `@return Html` [Html object](https://doc.nette.org/en/3.0/html-elements) of \<figure\> with \<img\> child.

> Uses `fri_theme_aspect_img_tag` filter, see [more about WP filters](./wp-filters.md).