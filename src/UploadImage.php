<?php

namespace Fronty\ResponsiveImages;

use Fronty\ResponsiveImages\Sizes\ImageSize;
use Fronty\ResponsiveImages\Sizes\ImageSizeList;
use Nette\Utils\Html;
use Nette\Utils\Strings;
use Nette\Utils\ArrayHash;
use enshrined\svgSanitize\Sanitizer;
use PHPHtmlParser\Dom;

/**
 * Object to manipulate with uploaded images.
 *	Get images from Cloudinary, Fly Dynamic Image Resizer plugin or by WP native functions.
 *  Big up for Adam Laita: https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#cloudinary
 *
 * @see https://github.com/junaidbhura/auto-cloudinary/wiki/Best-Practices
 */
class UploadImage extends BaseImage
{
	/** @var int */
	private $ID;

	/** @var Sanitizer */
	private $sanitizer;

	/**
	 * Get object for image uploaded in admin by WP attachment ID.
	 * @param int $ID
	 */
	public function __construct(int $ID)
	{
		$this->ID = $ID;
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): string
	{
		return wp_get_attachment_url($this->ID);
		// $src = wp_get_attachment_image_src($this->ID, 'full');
		// return ($src) ? $src[0] : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getPath(): string
	{
		return get_attached_file($this->ID);
	}

	/**
	 * Check if image is still uploaded in media library.
	 * @return bool
	 */
	public function hasImage(): bool
	{
		return (wp_get_attachment_image_src($this->ID) !== false);
	}

	/**
	 * Get image URL, width and height for given ImageSize.
	 * 	Crop is not performed if neither Auto Cloudinary and Fly Images plugins are available.
	 *  For Cloudinary crop values, see https://cloudinary.com/documentation/resizing_and_cropping
 	 * @return array [0 => URL, 1 => image width, 2 => image height]
	 */
	public function getSizedSrc(ImageSize $size): array
	{
		if (!$this->hasImage()) return ['', 0, 0];

		$width = $size->getWidth();
		$height = $size->getHeight();

		if ($this->isSvg()) {
			$src = wp_get_attachment_image_src($this->ID, [$width, $height]);
			$src[1] = $width;
			$src[2] = $height;
			return $src;
		}

		if (function_exists('cloudinary_url')) {
			return [cloudinary_url($this->ID, ['transform' => $size->getCloudinaryTransform()]), $width, $height];
		}
		if (function_exists('fly_get_attachment_image_src')) {
			return array_values(fly_get_attachment_image_src($this->ID, [$width, $height], $size->getCropOrPosition()));
		}
		return wp_get_attachment_image_src($this->ID, [$width, $height]);
	}

	/**
	 * Create link to image of given size.
	 * 	Useful when creating links to open image in lightbox.
	 * @param array $attrs Array of link HTML attributes.
	 * @param ImageSize $size Size to which to resize the targeted image. Null - full image will be targeted.
	 * @return Html
	 *
	 * @see static::getSizedSrc()
	 */
	public function getImgLink(array $attrs = [], ImageSize $size = null): Html
	{
		$attrs['href'] = ($size === null) ? $this->getUrl() : $this->getSizedSrc($size)[0];
		return Html::el('a', $attrs);
	}

	/**
	 * @inheritDoc
	 */
	public function getImgTag(ImageSize $size, array $attrs = []): Html
	{
		$src = $this->getSizedSrc($size);
		if ($src) {
			$attrs['src'] = esc_url($src[0]);
			$attrs['width'] = $src[1];
			$attrs['height'] = $src[2];
		}
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = esc_attr(strip_tags($attrs['alt']));
		$el = Html::el('img', $attrs);
		apply_filters('fri_upload_img_tag', $el, $size);
		return $el;
	}

	/**
	 * Get <img> tag with responsive sizes and srcset according to given ImageSizeList.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 * @return Html
	 */
	public function getResponsiveImgTag(ImageSizeList $sizes, array $attrs = []): Html
	{
		$widest = $sizes->getWidest();
		$maxWidth = $widest->getWidth();
		$maxHeight = $widest->getHeight();

		if ($this->isSvg()) {
			return $this->getImgTag(new ImageSize($maxWidth, $maxHeight ?? $maxWidth), $attrs);
		}

		$sets = $this->createSrcsetSizes($sizes);
		$widestSrc = $this->getSizedSrc($widest);

		if (isset($widestSrc[0])) {
			$attrs = array_merge($attrs, [
				'src' => $widestSrc[0],
				'sizes' => implode(', ', $sets->sizes),
				'srcset' => implode(', ', $sets->srcsets)
			]);
		}

		if (!isset($attrs['width']) && $widestSrc) {
			$attrs['width'] = $widestSrc[1];
			$attrs['height'] = $widestSrc[2];
		}
		if (!isset($attrs['alt'])) $attrs['alt'] = $this->getDefaultAlt();
		$attrs['alt'] = esc_attr(strip_tags($attrs['alt']));
		$el = Html::el('img', $attrs);

		apply_filters('fri_upload_responsive_img_tag', $el, $sizes);
		return $el;
	}

	/**
	 * Output responsive image tag.
	 * @param ImageSizeList $sizes
	 * @param array $attrs
	 *
	 * @see static::getResponsiveImgTag()
	 */
	public function responsiveImgTag(ImageSizeList $sizes, array $attrs = [])
	{
		echo $this->getResponsiveImgTag($sizes, $attrs);
	}

	/**
	 * Get image tag wrapped in Bootstrap 5 figure.ratio, which ensures it's aspect ratio.
	 * 	Useful with lazyloaded images to prevent Core Web Vitals CLS.
	 * 	To use with responsive img tag, set $sizes argument.
	 * @param int $width Image width from which count ratio.
	 * @param int $height Image height from which to count ratio.
	 * @param ImageSizeList|null $sizes If given, <img> tag will be generated using getResponsiveImgTag().
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 * @return Html
	 *
	 * @see https://getbootstrap.com/docs/5.0/helpers/ratio/#custom-ratios
	 * @see https://web.dev/cls/
	 */
	public function getAspectImgTag(int $width, int $height, ImageSizeList $sizes = null, array $wrapAttrs = [], array $imgAttrs = []): Html
	{
		if ($sizes === null) $img = $this->getImgTag(new ImageSize($width, $height), $imgAttrs);
		else $img = $this->getResponsiveImgTag($sizes, $imgAttrs);

		$ratio = $height / $width * 100;
		$el = Html::el('figure', $wrapAttrs)
			->addClass('ratio')
			->setStyle('--bs-aspect-ratio:' . $ratio . '%')
			->addHtml($img);
		apply_filters('fri_upload_aspect_img_tag', $el, $width, $height, $ratio, $sizes);
		return $el;
	}

	/**
	 * Output image tag wrapped in fixed ratio div.
	 * @param int $width
	 * @param int $height
	 * @param ImageSizeList $sizes
	 * @param array $wrapAttrs
	 * @param array $imgAttrs
	 *
	 * @see static::getAspectImgTag()
	 */
	public function aspectImgTag(int $width, int $height, ImageSizeList $sizes = null, array $wrapAttrs = [], array $imgAttrs = [])
	{
		echo $this->getAspectImgTag($width, $height, $sizes, $wrapAttrs, $imgAttrs);
	}

	/**
	 * Add inline SVG sanitize.
	 * @inheritDoc
	 */
	public function getInlineSvg(): string
	{
		return $this->getSanitizer()->sanitize(parent::getInlineSvg());
	}

	/**
	 * Create arrays of `srcset` and `sizes` HTML attributes for img tag.
	 * @param ImageSizeList $imageSizes
	 * @return ArrayHash
	 */
	private function createSrcsetSizes(ImageSizeList $imageSizes): ArrayHash
	{
		$sizes = $srcsets = [];
		foreach ($imageSizes as $viewport => $size) {
			$src = $this->getSizedSrc($size);
			if (!$src) continue;

			if ($imageSizes->isLast()) {
				$sizes[] = $src[1] . 'px';
			} else {
				$media = ($imageSizes->isMobileFirst()) ? 'min-width' : 'max-width';
				$sizes[] = sprintf('(%s: %dpx) %dpx', $media, $viewport, $src[1]);
			}
			$srcsets[] = esc_url($src[0]) . ' ' . $src[1] . 'w';
		}

		return ArrayHash::from([
			'sizes' => $sizes,
			'srcsets' => $srcsets
		], false);
	}

	/**
	 * Get default image alt.
	 * @return string
	 */
	private function getDefaultAlt(): string
	{
		if ($alt = get_post_meta($this->ID, '_wp_attachment_image_alt', true)) return $alt;
		if ($alt = get_the_title($this->ID)) return $alt;
		return '';
	}

	/**
	 * Get SVG sanitizer.
	 * @return Sanitizer
	 */
	private function getSanitizer(): Sanitizer
	{
		if (!$this->sanitizer) {
			$this->sanitizer = new Sanitizer();
			$this->sanitizer->minify(true);
		}
		return $this->sanitizer;
	}

	/**
	 * Replace core/image Gutenberg block with responsiveImageTag.
	 * @param string $blockContent HTML content of block.
	 * @param ImageSizeList $sizes
	 * @return string
	 * @see https://naswp.cz/mistrovska-optimalizace-obrazku-nejen-pro-wordpress/#nove-workflow-dodatek-obrazky-v-gutenbergu
	 */
	public function replaceImageBlock(string $blockContent, ImageSizeList $sizes): string
	{
		$dom = new DOM();
		$dom->loadStr($blockContent);

		$div = $dom->find('div', 0);
		$figure = $dom->find('figure', 0);
		$link = $dom->find('a', 0);
		$img = $dom->find('img', 0);
		$caption = $dom->find('figcaption', 0);
		if (Strings::lower(pathinfo($img->src, PATHINFO_EXTENSION)) === 'svg') return $blockContent;

		$class = $figure->class;
		if ($div) $class .= ' ' . $div->class;

		$fig = Html::el('figure', ['class' => $class, 'id' => $figure->id]);
		if ($link) {
			$imgParent = Html::el('a', ['class' => $link->class, 'href' => $link->href, 'target' => $link->target, 'rel' => $link->rel]);
			$fig->addHtml($imgParent);
		} else {
			$imgParent = $fig;
		}

		$imgTag = $this->getResponsiveImgTag($sizes, [
			'class' => $img->class,
			'id' => $img->id,
			'alt' => $img->alt,
			'title' => $img->title
		]);
		$imgParent->addHtml($imgTag);

		if ($caption && $caption->innerHtml) {
			$fig->addHtml($caption);
		}

		return (string)$fig;
	}
}