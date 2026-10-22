# Working with image sizes

To work with responsive images, we first has to prepare one or more image sizes for various breakpoints. Image sizes can be defined using following classes:
- [`Fronty\ResponsiveImages\Sizes\ImageSize`](#imagesize-object) is representation of image dimensions and transformations. This class alone is used for non-responsive images, where only one size is required.
- [`Fronty\ResponsiveImages\Sizes\ImageSizeList`](#imagesizelist-object) is representation of group of ImageSize objects and is used for responsive images, where different size is required for each breakpoint.
- [`Fronty\ResponsiveImages\Sizes\BootstrapSizes`](#bootstrapsizes-object) is helper class to generate ImageSizeList according to specified container width and grid columns.

----------------------------------------------------------------
## ImageSize object

This object holds and calculates information about image width, height and transformations. Arguments can only be set through `__construct`:

- `int $width` Required image width in pixels. The only required argument.
- `int|null $height` Optional image height in pixels. Is proportionaly calculated if not given.
- `string|bool|null $crop` Whether and how to crop image.
	- `null` Auto decision - crop in *fill* mode if $height given, don't crop (*fit* mode) if $height is null.
	- `string` *fill* (crop), *fit* (don't crop) or any [supported crop/resize mode name](https://cloudinary.com/documentation/resizing_and_cropping#resize_and_crop_modes) with Auto Cloudinary plugin. Fly Dynamic Image Resizer plugin doesn't support crop modes, so values *crop*, *fill*, *lfill*, *fill_pad*, *thumb* are translated as crop, others values as no-crop.
	- `bool` values are translated to string: `true` = crop in *fill*, `false` = don't crop (*fit* mode)
- `array $cloudinaryTransform` Definition of other [Cloudinary transformations](https://cloudinary.com/documentation/image_transformations). Ignored if used with Fly Dynamic Image Resizer plugin.

### Examples:
```php
use Fronty\ResponsiveImages\Sizes\ImageSize;

// Resize image proportionaly to 500px width, don't crop, default Cloudinary transformations only
$size1 = new ImageSize(500);

// Crop image to exactly 300x200px, default Cloudinary transformations only
$size2 = new ImageSize(300, 200);

// Resize image so it fits to 500x300px bounds, don't crop, default Cloudinary transformations only
$size3 = new ImageSize(500, 300, false); // the same with $crop = 'fit'

// Resize and crop image to 250x250px, prevent face croping
// @see https://cloudinary.com/documentation/face_detection_based_transformations
$size4 = new ImageSize(250, 250, 'thumb', ['gravity' => 'face']);

// The same using suggested factory function
$size1 = img_size(500);
$size2 = img_size(300, 200);
$size3 = img_size(500, 300, false);
$size4 = img_size(250, 250, 'thumb', ['gravity' => 'face']);
```

### Default Cloudinary transformation:

ImageSize object comes with default Cloudinary transformations to change image quality auto:eco and to fetch images in WebP format if supported by browser. This transformation is applied to all images, you can change it through public static property:

```php
// setup-images.php
use Fronty\ResponsiveImages\Sizes\ImageSize;

ImageSize::$defaultCloudinaryTransform = [
	'quality' => 80 // All images will now be in 80% quality rather then eco
];
```

### Public API:
- `getWidth(): int` Get image width in pixels.
- `getHeight(): ?int` Get image height in pixels if defined.
- `getCropType(): string` Normalize given crop type to string as desbribed above.
- `hasCrop(): bool` Normalize given crop type to boolean.
- `getCloudinaryTransform(): array` Merge Cloudinary transformation from construct with default values.
- `getScaled(float $scale): ImageSize` Get copy of the size with both dimensions multiplied by given $scale, eg. by a pixel density. Max width and height are applied before the multiplication, so they don't limit the copy again.

---

## ImageSizeList object

ImageSizeList represents a group of ImageSize objects related to breakpoints. ImageSize objects can be given either from array or by fluent interface. It also holds information whether the sizes were given in mobile-first or desktop-first order.

### Public API:
- `__construct(bool $mobileFirst = true, int|null $maxWidth = null, int|null $maxHeight = null, array $densities = [1, 2])` Instantiate for either mobile- or desktop-frist notation
	- `bool $mobileFirst` default `true` = mobile-first, `false` = desktop-first notation
	- `int|null $maxWidth` Maximal image width, which won't be exceeded in any breakpoint.
	- `int|null $maxHeight` Maximal image height, which won't be exceeded in any breakpoint.
	- `array $densities` Pixel densities (DPR), in which to offer every declared width in `srcset`. See [Pixel densities](#pixel-densities).
- `static fromArray(array $imageSizes, bool $mobileFirst = true): ImageSizeList` Instantiate using array of breakpoint widths (keys) and arguments for ImageSize object (values).
	- `array $imageSizes`
		- `int` array key: min-width or max-width breakpoint (according to $mobileFirst argument)
		- `int|array` array value: width only, or array of all ImageSize arguments
	- `bool $mobileFirst` default `true` = mobile-first, `false` = desktop-first notation
- `append(int $breakpoint, ImageSize $size): ImageSizeList` Append new ImageSize for given breakpoint, returns self object to chain.
- `isMobileFirst(): bool` Is mobile-first notation active?
- `setDensities(array $densities): ImageSizeList` Set pixel densities, in which to offer every declared width in `srcset`. Supports chaining. Throws `InvalidArgumentException` if no positive density is given. See [Pixel densities](#pixel-densities).
- `getDensities(): array` Get pixel densities sorted from the smallest one.
- `isEmpty(): bool` Is there any ImageSize?
- `getWidest(): ImageSize` Get widest image size. Throws `RuntimeException` if list is empty.

Class implements `Iterator` interface so it can be use in loops to iterate through ImageSize objects. ImageSize objects are sorted by breakpoint width from smallest with destop-first or widest with mobile-first notation before each loop to ensure sizes input order doesn't matter.

### Examples:

```php
use Fronty\ResponsiveImages\Sizes\ImageSizeList;
use Fronty\ResponsiveImages\Sizes\ImageSize;

// Create using mobile-first notation and fluent interface
$sizes1 = (new ImageSizeList())
	->append(0, new ImageSize(400, 250)) // Fallback when no media query matched
	->append(576, new ImageSize(500)) // (min-width: 576px), up to next breakpoint (767px)
	->append(768, new ImageSize(700)) // (min-width: 768px), up to next breakpoint (991px)
	->append(992, new ImageSize(900)) // (min-width: 992px), up to next breakpoint (1200px)
	->append(1200, new ImageSize(1100)); // (min-width: 1200px) without upper limit

// Create using desktop-first notation and fluent interface
$sizes2 = (new ImageSizeList(false))
	->append(575, new ImageSize(400, 250)) // from 0px, (max-width: 575px)
	->append(767, new ImageSize(700)) // form previous breakpoint 576px, (max-width: 767px)
	->append(991, new ImageSize(900)) // form previous breakpoint 768px, (max-width: 992px)
	->append(1199, new ImageSize(1100)); // from previous breakpoint 992px up to infinity - largest breakpoints doesn't have maximum


// Same desktop-first notation using array
$sizes2 = ImageSizeList::fromArray([
	576 => [400, 250]
	768 => 700,
	992 => 900,
	1200 => 1100
], false);

// Same desktop-first notation using suggested factory function
$sizes2 = img_sizes([
	576 => [400, 250]
	768 => 700,
	992 => 900,
	1200 => 1100
], false);
```

### Pixel densities

The `sizes` attribute tells the browser how wide the image slot is in CSS pixels, `srcset` offers the files to choose from. On a display with DPR 2 the browser looks for a candidate twice as wide as the slot, so a `srcset` built from the declared widths only has nothing dense enough to offer in the widest breakpoint - above it the ladder simply ends.

That's why every declared width is offered in all pixel densities of the list, `[1, 2]` by default, ie. each width and it's double:

```php
// Default [1, 2]: a 1100px wide slot is offered as both 1100w and 2200w
$sizes = img_sizes([
	0 => [400, 250],
	1200 => 1100
]);

// Add 1.5x candidates for mid-DPR displays
$sizes->setDensities([1, 1.5, 2]);

// Opt out of retina candidates completely
$sizes->setDensities([1]);

// Densities can be set in the constructor as well
$sizes = new ImageSizeList(true, null, null, [1, 2, 3]);
```

Candidates are:
- **limited by the original image**, so nothing is upscaled - a 2048px wide original ends the ladder at 2048w even when the 2x candidate would be 2200w,
- **deduplicated by the really returned width**, so densities clamped to the same width are offered just once,
- **checked for the required aspect ratio** - a cropped candidate, which the resizer wasn't able to crop as required, is dropped instead of being offered with a different crop.

The `sizes` attribute is not affected by densities at all, it always describes the slot width as declared.

> Every density means one more image to resize and to store on the server. Keep the list short, `[1, 2]` covers the vast majority of displays.

---

## BootstrapSizes object

If you work with Bootstrap grid system in your theme, you don't have to measure image on each breakpoint, instead you can generate it's sizes based on number of columns, which the image takes in Bootstrap grid system.

### Configuration

`BootstrapSizes` object is preconfigured with default Bootstrap v5 values. If you compile custom breakpoints, container widths, number of columns or gutter in SCSS, you need to set the same values to the object's constructor:

```php
use Fronty\ResponsiveImages\Sizes\BootstrapSizes;

// Number of columns in grid (default 12)
$columns = 10;

// Gap between two columns in pixels (default 24)
$gutter = 30;

// Breakpoints - viewport widths, where the layout is changed.
// Default: [ 'xs' => 0, 'sm' => 576, 'md' => 768, 'lg' => 992, 'xl' => 1200, 'xxl' => 1400 ]
$breakpoints = [
	'xxs' => 0,
	'xs' => 375,
	'sm' => 576,
	'md' => 768,
	'lg' => 992,
	'xl' => 1200,
	'xxl' => 1400
];

// Container widths for each breakpoint, omit if container should be 100% wide
// Default: [ 'sm' => 540, 'md' => 720, 'lg' => 960, 'xl' => 1140, 'xxl' => 1320 ]
$containers = [
	'sm' => 520,
	'md' => 700,
	'lg' => 920,
	'xl' => 1150,
	'xxl' => 1350
];

$bootstrap = new BootstrapSizes($columns, $gutter, $breakpoints, $containers);
```

If you want to use this configuration for all instances of BootstrapSizes, it's a good idea to configure the object in factory function as [suggested in factory function configuration](./configuration.md#factory-functions).

> Thanks to this configuration, BootstrapSizes helper might work even with different grid systems than Bootstrap.

### Public API

- `__construct(int $columns, int $gutter, array $breakpoints, array $containers)` Instantiate with configuration. All arguments are `null` by default, which means they are set as default Bootstrap 5 grid (see [configuration above](#configuration)).
- `col(string $breakpoint, int $cols, int $height = null, $crop = null, array $cloudinaryTransform = []): BootstrapSizes` Set how many $columns does the image takes on given $breakpoint. Arguments $height, $crop and $cloudinaryTransform are used in ImageSize object generated for this breakpoint. Supports chaining.
- `container(string $breakpoint, int|null $width, bool $givenWithGutter = true): BootstrapSizes` Change container size for $breakpoint to $width in pixels given either with (default) or without gutter padding. Useful if image container is nested inside parent column or has non-standard width. Supports chaining.
- `getSizes(int|null $maxWidth = null, int|null $maxHeight = null, float $hdRatio = 1): ImageSizeList` Get calculated ImageSizeList based on previously given cols. Arguments $maxWidth and $maxHeight are widths and heights, which won't be exceeded in any breakpoint. Argument $hdRatio multiplies all calculated dimensions, including the slot widths declared in the `sizes` attribute.

> Keep $hdRatio at `1` unless you really want to declare larger slots than the layout has: retina candidates are offered by `srcset` [pixel densities](#pixel-densities) of the returned ImageSizeList.

Since Bootstrap works with mobile-first breakpoints, calculated `ImageSizeList` will also be returned in mobile-first notation.

By default all breakpoints are set to 12 columns, so they fill the full width of container. For smallest xs breakpoint the default Bootstrap container is fluid, image width will be calculated as maximal possible viewport width minus $gutter (both side paddings). Maximal viewport width is calculated as next breakpoint minus 1px (mobile-first notation). This applies to all fluid containers.

Similar as in Bootstrap, number of columns and other arguments given in `col()` method will be also used for every wider breakpoint, which is not explicitly specified.

### Examples

Image inside the default root Bootstrap container:

```php
use Fronty\ResponsiveImages\Sizes\BootstrapSizes;
use Fronty\ResponsiveImages\UploadImage;

// Create responsive image scaled to whole Bootstrap container in all breakpoints (no col is defined)
$sizes1 = (new BootstrapSizes())->getSizes();

// Get image scaled by given number of columns for each breakpoint
$sizes2 = (new BootstrapSizes())
	// From 0 to 575px viewports image will be scaled to 551x300px. Width 551px is calculated as next breakpoint width 576px minus 1px minus $gutter (side paddings) 24px.
	->col('xs', 12, 300)

	// From 576px to 991px the image will be scaled as 10/12 of container width. Height will be proportional.
	->col('sm', 10)

	// Breakpoint 'md' was skipped, so 10 columns will apply for md container as well

	// From 992px to 1199px viewports image will be scaled to 8/12 of container, proportional height.
	->col('lg', 8)

	// For 1200px and wider viewport image will scale to 6/12 of container, proportional height.
	->col('xl', 6)

	->getSizes();

// The same with suggested factory function
$sizes1 = bootstrap_sizes()->getSizes();
$sizes2 = bootstrap_sizes()
	->col('xs', 12, 300)
	->col('sm', 10)
	->col('lg', 8)
	->col('xl', 6)
	->getSizes();
```

HTML:
```html
<div class="container">
	<!-- $sizes1 fills the width of whole container -->
	<?php (new UploadImage($attachment_id))->responsiveImgTag($sizes1); ?>

	<div class="row">
		<!-- Here we use the same columns defined for $size2 -->
		<div class="col-sm-10 col-lg-8 col-xl-6">
			<?php (new UploadImage($attachment_id))->responsiveImgTag($sizes2); ?>
		</div>
	</div>
</div>
```

Image can be embed inside the container with changed width. In following example, image container is 10/12 wide from md and 8/12 wide from xl. The best way is to measure the width of parent .col in Chrome inspector.

Containers doesn't work like cols, their widths are not scaled automaticaly for next breakpoints, so if you change md container, you have to change all wider container (lg, xl and xxl) as well. This downside might be fixed in some next version.

```php
use Fronty\ResponsiveImages\Sizes\BootstrapSizes;
use Fronty\ResponsiveImages\UploadImage;

// Same columns as in $size2, but change container from lg
$sizes3 = (new BootstrapSizes())
	// The width of parent .col is 10/12 of root container, default md container is 720px, so 720*10/12 = 600px ($gutter included)
	->container('md', 600)

	// We have to specify changed container for lg, it is not scaled automatically. Calculation would be: 960px * 10/12 = 800px, where 960px is default root container width for lg.
	->container('lg', 800)

	// For sake of example, we set xl container without $gutter. Root xl container is 1140px, we use it's 8/12th, so the value would be: 1140 * 8/12 - 24 = 736px
	->container('xl', 736, false)

	// For xxl breakpoint, we also use 8/12th of root container width: 1320 * 8/12 = 880px
	->container('xxl', 880)

	// Set nested cols
	->col('xs', 12, 300)
	->col('sm', 10)
	->col('lg', 8)
	->col('xl', 6)
	->getSizes();

// Same with suggested factory function
$sizes3 = bootstrap_sizes()
	->container('md', 600)
	->container('lg', 800)
	->container('xl', 736, false)
	->container('xxl', 880)
	->col('xs', 12, 300)
	->col('sm', 10)
	->col('lg', 8)
	->col('xl', 6)
	->getSizes();
```

HTML:
```html
<div class="container">
	<div class="row">
		<!-- Change the width of image container from md -->
		<div class="col-md-10 col-xl-8">

			<div class="row">
				<div class="col-sm-10 col-lg-8 col-xl-6">
					<?php (new UploadImage($attachment_id))->responsiveImgTag($sizes3); ?>
				</div>
			</div>

		</div>
	</div>
</div>
```