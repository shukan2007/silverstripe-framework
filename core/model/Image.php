<?php
/**
 * Represents an image attached to a page.
 * @package sapphire
 * @subpackage filesystem
 */
class Image extends File {
	
	const ORIENTATION_SQUARE = 0;
	const ORIENTATION_PORTRAIT = 1;
	const ORIENTATION_LANDSCAPE = 2;
	
	static $casting = array(
		'Tag' => 'HTMLText',
	);

	/**
	 * The width of an image thumbnail in a strip.
	 * @var int
	 */
	public static $strip_thumbnail_width = 50;
	
	/**
	 * The height of an image thumbnail in a strip.
	 * @var int
	 */
	public static $strip_thumbnail_height = 50;
	
	/**
	 * The width of an image thumbnail in the CMS.
	 * @var int
	 */
	public static $cms_thumbnail_width = 100;
	
	/**
	 * The height of an image thumbnail in the CMS.
	 */
	public static $cms_thumbnail_height = 100;
	
	/**
	 * The width of an image thumbnail in the Asset section.
	 */
	public static $asset_thumbnail_width = 100;
	
	/**
	 * The height of an image thumbnail in the Asset section.
	 */
	public static $asset_thumbnail_height = 100;
	
	/**
	 * The width of an image preview in the Asset section.
	 */
	public static $asset_preview_width = 400;
	
	/**
	 * The height of an image preview in the Asset section.
	 */
	public static $asset_preview_height = 200;
	
	/**
	 * Set up template methods to access the transformations generated by 'generate' methods.
	 */
	public function defineMethods() {
		$methodNames = $this->allMethodNames();
		foreach($methodNames as $methodName) {
			if(substr($methodName,0,8) == 'generate') {
				$this->addWrapperMethod(substr($methodName,8), 'getFormattedImage');
			}
		}
		
		parent::defineMethods();
	}
	
	/**
	 * An image exists if it has a filename.
	 * @return boolean
	 */
	public function exists() {
		if(isset($this->record["Filename"])) {
			return true;
		}		
	}

	/**
	 * Get the relative URL for this Image.
	 * Overwrites File->URL() which returns an absolute URL.
	 * 
	 * @todo Refactor to return absolute URL like {@link File}
	 * @uses Director::baseURL()
	 * @return string
	 */
	function getURL() {
		return Director::baseURL() . $this->Filename;
	}
	
	/**
	 * Return an XHTML img tag for this Image.
	 * @return string
	 */
	function getTag() {
		if(file_exists("../" . $this->Filename)) {
			$url = $this->URL();
			$title = ($this->Title) ? $this->Title : $this->Filename;
			return "<img src=\"$url\" alt=\"$title\" />";
		}
	}
	
	/**
	 * Return an XHTML img tag for this Image.
	 * @return string
	 */
	function forTemplate() {
		return $this->Tag();
	}
		
	/**
	 * Load a recently uploaded image into this image field.
	 * @param array $tmpFile The array entry from $_FILES
	 * @return boolean Returns true if successful
	 */
	function loadUploaded($tmpFile) {
		if(parent::loadUploaded($tmpFile) === true) {
			$this->deleteFormattedImages();
			return true;
		}
	}

	function loadUploadedImage($tmpFile) {
		if(!is_array($tmpFile)) {
			user_error("Image::loadUploadedImage() Not passed an array.  Most likely, the form hasn't got the right enctype", E_USER_ERROR);
		}
		
		if(!$tmpFile['size']) {
			return;
		}
		
		$class = $this->class;

		// Create a folder		
		if(!file_exists(ASSETS_PATH)) {
			mkdir(ASSETS_PATH, Filesystem::$folder_create_mask);
		}
		
		if(!file_exists(ASSETS_PATH . "/$class")) {
			mkdir(ASSETS_PATH . "/$class", Filesystem::$folder_create_mask);
		}

		// Generate default filename
		$file = str_replace(' ', '-',$tmpFile['name']);
		$file = ereg_replace('[^A-Za-z0-9+.-]+','',$file);
		$file = ereg_replace('-+', '-',$file);
		if(!$file) {
			$file = "file.jpg";
		}
		
		$file = ASSETS_PATH . "/$class/$file";
		
		while(file_exists(BASE_PATH . "/$file")) {
			$i = $i ? ($i+1) : 2;
			$oldFile = $file;
			$file = ereg_replace('[0-9]*(\.[^.]+$)',$i . '\\1', $file);
			if($oldFile == $file && $i > 2) user_error("Couldn't fix $file with $i", E_USER_ERROR);
		}
		
		if(file_exists($tmpFile['tmp_name']) && copy($tmpFile['tmp_name'], BASE_PATH . "/$file")) {
			// Remove the old images

			$this->deleteFormattedImages();
			return true;
		}
	}
	
	public function SetWidth($width) {
		return $this->getFormattedImage('SetWidth', $width);
	}
	
	public function SetHeight($height) {
		return $this->getFormattedImage('SetHeight', $height);
	}
	
	public function SetSize($width, $height) {
		return $this->getFormattedImage('SetSize', $width, $height);
	}
	
	/**
	 * Resize this Image by width, keeping aspect ratio. Use in templates with $SetWidth.
	 * @return GD
	 */
	public function generateSetWidth(GD $gd, $width) {
		return $gd->resizeByWidth($width);
	}
	
	/**
	 * Resize this Image by height, keeping aspect ratio. Use in templates with $SetHeight.
	 * @return GD
	 */
	public function generateSetHeight(GD $gd, $height){
		return $gd->resizeByHeight($height);
	}
	
	/**
	 * Resize this Image by both width and height, using padded resize. Use in templates with $SetSize.
	 * @return GD
	 */
	public function generateSetSize(GD $gd, $width, $height) {
		return $gd->paddedResize($width, $height);
	}
	
	public function CMSThumbnail() {
		return $this->getFormattedImage('CMSThumbnail');
	}
	
	/**
	 * Resize this image for the CMS. Use in templates with $CMSThumbnail.
	 * @return GD
	 */
	function generateCMSThumbnail(GD $gd) {
		return $gd->paddedResize($this->stat('cms_thumbnail_width'),$this->stat('cms_thumbnail_height'));
	}
	
	/**
	 * Resize this image for preview in the Asset section. Use in templates with $AssetLibraryPreview.
	 * @return GD
	 */
	function generateAssetLibraryPreview(GD $gd) {
		return $gd->paddedResize($this->stat('asset_preview_width'),$this->stat('asset_preview_height'));
	}
	
	/**
	 * Resize this image for thumbnail in the Asset section. Use in templates with $AssetLibraryThumbnail.
	 * @return GD
	 */
	function generateAssetLibraryThumbnail(GD $gd) {
		return $gd->paddedResize($this->stat('asset_thumbnail_width'),$this->stat('asset_thumbnail_height'));
	}
	
	/**
	 * Resize this image for use as a thumbnail in a strip. Use in templates with $StripThumbnail.
	 * @return GD
	 */
	function generateStripThumbnail(GD $gd) {
		return $gd->croppedResize($this->stat('strip_thumbnail_width'),$this->stat('strip_thumbnail_height'));
	}
	
	function generatePaddedImage(GD $gd, $width, $height) {
		return $gd->paddedResize($width, $height);
	}

	/**
	 * Return an image object representing the image in the given format.
	 * This image will be generated using generateFormattedImage().
	 * The generated image is cached, to flush the cache append ?flush=1 to your URL.
	 * @param string $format The name of the format.
	 * @param string $arg1 An argument to pass to the generate function.
	 * @param string $arg2 A second argument to pass to the generate function.
	 * @return Image_Cached
	 */
	function getFormattedImage($format, $arg1 = null, $arg2 = null) {
		if($this->ID && $this->Filename && Director::fileExists($this->Filename)) {
			$cacheFile = $this->cacheFilename($format, $arg1, $arg2);

			if(!file_exists("../".$cacheFile) || isset($_GET['flush'])) {
				$this->generateFormattedImage($format, $arg1, $arg2);
			}
			
			return new Image_Cached($cacheFile);
		}
	}
	
	/**
	 * Return the filename for the cached image, given it's format name and arguments.
	 * @param string $format The format name.
	 * @param string $arg1 The first argument passed to the generate function.
	 * @param string $arg2 The second argument passed to the generate function.
	 * @return string
	 */
	function cacheFilename($format, $arg1 = null, $arg2 = null) {
		$folder = $this->ParentID ? $this->Parent()->Filename : ASSETS_DIR . "/";
		
		$format = $format.$arg1.$arg2;
		
		return $folder . "_resampled/$format-" . $this->Name;
	}
	
	/**
	 * Generate an image on the specified format. It will save the image
	 * at the location specified by cacheFilename(). The image will be generated
	 * using the specific 'generate' method for the specified format.
	 * @param string $format Name of the format to generate.
	 * @param string $arg1 Argument to pass to the generate method.
	 * @param string $arg2 A second argument to pass to the generate method.
	 */
	function generateFormattedImage($format, $arg1 = null, $arg2 = null) {
		$cacheFile = $this->cacheFilename($format, $arg1, $arg2);
	
		$gd = new GD("../" . $this->Filename);
		
		
		if($gd->hasGD()){
			$generateFunc = "generate$format";		
			if($this->hasMethod($generateFunc)){
				$gd = $this->$generateFunc($gd, $arg1, $arg2);
				if($gd){
					$gd->writeTo("../" . $cacheFile);
				}
	
			} else {
				USER_ERROR("Image::generateFormattedImage - Image $format function not found.",E_USER_WARNING);
			}
		}
	}
	
	/**
	 * Generate a resized copy of this image with the given width & height.
	 * Use in templates with $ResizedImage.
	 */
	function generateResizedImage($gd, $width, $height) {
		if(is_numeric($gd) || !$gd){
			USER_ERROR("Image::generateFormattedImage - generateResizedImage is being called by legacy code or gd is not set.",E_USER_WARNING);
		}else{
			return $gd->resize($width, $height);
		}
	}

	/**
	 * Generate a resized copy of this image with the given width & height, cropping to maintain aspect ratio.
	 * Use in templates with $CroppedImage
	 */
	function generateCroppedImage($gd, $width, $height) {
		return $gd->croppedResize($width, $height);
	}
	
	/**
	 * Remove all of the formatted cached images.
	 * Should be called by any method that updates the current image.
	 */
	public function deleteFormattedImages() {
		if($this->Filename) {
			$numDeleted = 0;
			$methodNames = $this->allMethodNames();
			$numDeleted = 0;
			foreach($methodNames as $methodName) {
				if(substr($methodName,0,8) == 'generate') {
					$format = substr($methodName,8);
					$cacheFile = $this->cacheFilename($format);
					if(Director::fileExists($cacheFile)) {
						unlink(Director::getAbsFile($cacheFile));
						$numDeleted++;
					}
				}
			}
			return $numDeleted;
		}
	}
	
	/**
	 * Get the dimensions of this Image.
	 * @param string $dim If this is equal to "string", return the dimensions in string form,
	 * if it is 0 return the height, if it is 1 return the width.
	 * @return string|int
	 */
	function getDimensions($dim = "string") {
		if($this->getField('Filename')) {
			$imagefile = Director::baseFolder() . '/' . $this->getField('Filename');
			if(file_exists($imagefile)) {
				$size = getimagesize($imagefile);
				return ($dim === "string") ? "$size[0]x$size[1]" : $size[$dim];
			} else {
				return ($dim === "string") ? "file '$imagefile' not found" : null;
			}
		}
	}

	/**
	 * Get the width of this image.
	 * @return int
	 */
	function getWidth() {
		return $this->getDimensions(0);
	}
	
	/**
	 * Get the height of this image.
	 * @return int
	 */
	function getHeight() {
		return $this->getDimensions(1);
	}
	
	/**
	 * Get the orientation of this image.
	 * @return 	ORIENTATION_SQUARE | ORIENTATION_PORTRAIT | ORIENTATION_LANDSCAPE
	 */
	function getOrienation() {
		$width = $this->getWidth();
		$height = $this->getHeight();
		if($width > $height) {
			return self::ORIENTATION_LANDSCAPE;
		} elseif($height > $width) {
			return self::ORIENTATION_PORTRAIT;
		} else {
			return self::ORIENTATION_SQUARE;
		}
	}
	
	
	
	// ###################
	// DEPRECATED
	// ###################
	
	/**
	 * @deprecated Use getTag() instead
	 */
	function Tag() {
		return $this->getTag();
	}
	
	/**
	 * @deprecated Use getURL() instead
	 */
	function URL() {
		return $this->getURL();
	}
}

/**
 * A resized / processed {@link Image} object.
 * When Image object are processed or resized, a suitable Image_Cached object is returned, pointing to the
 * cached copy of the processed image.
 * @package sapphire
 * @subpackage filesystem
 */
class Image_Cached extends Image {
	/**
	 * Create a new cached image.
	 * @param string $filename The filename of the image.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.  Singletons
	 * don't have their defaults set.
	 */
	public function __construct($filename = null, $isSingleton = false) {
		parent::__construct(array(), $isSingleton);
		$this->Filename = $filename;
	}
	
	public function getRelativePath() {
		return $this->getField('Filename');
	}
	
	// Prevent this from doing anything
	public function requireTable() {
		
	}
	
	public function debug() {
		return "Image_Cached object for $this->Filename";
	}
}

/**
 * Uploader support for the uploading anything which is a File or subclass of File, eg Image.
 * Is connected to the URL routing "/image" through sapphire/_config.php,
 * and used by all iframe-based upload-fields in the CMS.
 *
 * Used by {@link FileIFrameField}, {@link ImageField}.
 * 
 * @todo Refactor to using FileIFrameField and ImageField as a controller for the upload,
 *   rather than something totally disconnected from the original Form and FormField
 *   context. Without the original context its impossible to control permissions etc.
 *
 * @package sapphire
 * @subpackage filesystem
 */
class Image_Uploader extends Controller {
	static $url_handlers = array(
		'$Action!/$Class!/$ID!/$Field!/$FormName!' => '$FormName',
		'$Action/$Class/$ID/$Field' => 'handleAction',
	);
	
	static $allowed_actions = array(
		'iframe' => 'CMS_ACCESS_CMSMain',
		'flush' => 'CMS_ACCESS_CMSMain',
		'save' => 'CMS_ACCESS_CMSMain',
		'delete' => 'CMS_ACCESS_CMSMain',
		'EditImageForm' => 'CMS_ACCESS_CMSMain',
		'DeleteImageForm' => 'CMS_ACCESS_CMSMain'
	);
	
	function init() {
		// set language
		$member = Member::currentUser();
		if(!empty($member->Locale)) {
			i18n::set_locale($member->Locale);
		}
		
		// set reading lang
		if(singleton('SiteTree')->hasExtension('Translatable') && !Director::is_ajax()) {
			Translatable::choose_site_locale(array_keys(Translatable::get_existing_content_languages('SiteTree')));
		}
		
		parent::init();
	}
	
	/**
	 * Ensures the css is loaded for the iframe.
	 */
	function iframe() {
		if(!Permission::check('CMS_ACCESS_CMSMain')) Security::permissionFailure($this);
		
		Requirements::css(CMS_DIR . "/css/Image_iframe.css");
		return array();
	}
	
	/**
	 * Image object attached to this class.
	 * @var Image
	 */
	protected $imageObj;
	
	/**
	 * Associated parent object.
	 * @var DataObject
	 */
	protected $linkedObj;
	
	/**
	 * Finds the associated parent object from the urlParams.
	 * @return DataObject
	 */
	function linkedObj() {
		if(!$this->linkedObj) {
			$this->linkedObj = DataObject::get_by_id($this->urlParams['Class'], $this->urlParams['ID']);
			if(!$this->linkedObj) {
				user_error("Data object '{$this->urlParams['Class']}.{$this->urlParams['ID']}' couldn't be found", E_USER_ERROR);
			}
		}				
		return $this->linkedObj;
	}
	
	/**
	 * Returns the Image object attached to this class.
	 * @return Image
	 */
	function Image() {
		if(!$this->imageObj) {
			$funcName = $this->urlParams['Field'];
			$linked = $this->linkedObj();
			$this->imageObj = $linked->obj($funcName);
			if(!$this->imageObj) {$this->imageObj = new Image(null);}
		}
		
		return $this->imageObj;
	}
	
	/**
	 * Returns true if the file attachment is an image.
	 * Otherwise, it's a file.
	 * @return boolean
	 */
	function IsImage() {
		$className = $this->Image()->class;
		return $className == "Image" || is_subclass_of($className, "Image");
	}

	function UseSimpleForm() {
		if(!$this->useSimpleForm) {
			$this->useSimpleForm = false;
		}
		return $this->useSimpleForm;
	}
	
	/**
	 * Return a link to this uploader.
	 * @return string
	 */
	function Link($action = null) {
		return $this->RelativeLink($action);
	}
	
	/**
	 * Return the relative link to this uploader.
	 * @return string
	 */
	function RelativeLink($action = null) {
		if(!$action) {
			$action = "index";
		}
		return "images/$action/{$this->urlParams['Class']}/{$this->urlParams['ID']}/{$this->urlParams['Field']}";
	}
	
	/**
	 * Form to show the current image and allow you to upload another one.
	 * @return Form
	 */
	function EditImageForm() {
		$isImage = $this->IsImage();
		$type =  $isImage ? _t('Controller.IMAGE', "Image") : _t('Controller.FILE', "File");
		if($this->Image()->ID) {
			$title = sprintf(
				_t('ImageUploader.REPLACE', "Replace %s", PR_MEDIUM, 'Replace file/image'), 
				$type
			);
			$fromYourPC = _t('ImageUploader.ONEFROMCOMPUTER', "With one from your computer");
			$fromTheDB = _t('ImageUplaoder.ONEFROMFILESTORE', "With one from the file store");
		} else {
			$title = sprintf(
				_t('ImageUploader.ATTACH', "Attach %s", PR_MEDIUM, 'Attach image/file'),
				$type
			);
			$fromYourPC = _t('ImageUploader.FROMCOMPUTER', "From your computer");
			$fromTheDB = _t('ImageUploader.FROMFILESTORE', "From the file store");
		}
		return new Form(
			$this, 
			'EditImageForm', 
			new FieldSet(
				new HiddenField("Class", null, $this->urlParams['Class']),
				new HiddenField("ID", null, $this->urlParams['ID']),
				new HiddenField("Field", null, $this->urlParams['Field']),
				new HeaderField('EditImageHeader',$title),
				new SelectionGroup("ImageSource", array(
					"new//$fromYourPC" => new FieldGroup("",
						new FileField("Upload","")
					),
					"existing//$fromTheDB" => new FieldGroup("",
						new TreeDropdownField("ExistingFile", "","File")
					)
				))
			),
			new FieldSet(
				new FormAction("save",$title)
			)
		);
	}
	
	/**
	 * A simple version of the upload form.
	 * @returns string
	 */
	function EditImageSimpleForm() {
		$isImage = $this->IsImage();
		$type =  $isImage ? _t('Controller.IMAGE') : _t('Controller.FILE');
		if($this->Image()->ID) {
			$title = sprintf(
				_t('ImageUploader.REPLACE'), 
				$type
			);
			$fromYourPC = _t('ImageUploader.ONEFROMCOMPUTER');
		} else {
			$title = sprintf(
				_t('ImageUploader.ATTACH'), 
				$type
			);
			$fromTheDB = _t('ImageUploader.ONEFROMFILESTORE');
		}
		
		return new Form($this, 'EditImageSimpleForm', new FieldSet(
			new HiddenField("Class", null, $this->urlParams['Class']),
			new HiddenField("ID", null, $this->urlParams['ID']),
			new HiddenField("Field", null, $this->urlParams['Field']),
			new FileField("Upload","")
		),
		new FieldSet(
			new FormAction("save",$title)
		));
	}
	
	/**
	 * A form to delete this image.
	 * @return string
	 */
	function DeleteImageForm() {
		if($this->Image()->ID) {
			$isImage = $this->IsImage();
			$type =  $isImage ? _t('Controller.IMAGE') : _t('Controller.FILE');
			$title = sprintf(
				_t('ImageUploader.DELETE', 'Delete %s', PR_MEDIUM, 'Delete file/image'), 
				$type
			);
			$form = new Form(
				$this,
				'DeleteImageForm', 
				new FieldSet(
					new HiddenField("Class", null, $this->urlParams['Class']),
					new HiddenField("ID", null, $this->urlParams['ID']),
					new HiddenField("Field", null, $this->urlParams['Field'])
				),
				new FieldSet(
					$deleteAction = new ConfirmedFormAction(
						"delete",
						$title, 
						sprintf(_t('ImageUploader.REALLYDELETE', "Do you really want to remove this %s?"), $type)
					)
				)
			);
			$deleteAction->addExtraClass('delete');
			
			return $form;
		}
	}
	
	/**
	 * Save the data in this form.
	 */
	function save($data, $form) {
		if($data['ImageSource'] != 'existing' && $data['Upload']['size'] == 0) {
			// No image has been uploaded
			Director::redirectBack();
			return;
		}
		$owner = DataObject::get_by_id($data['Class'], $data['ID']);
		$fieldName = $data['Field'] . 'ID';
		
		if($data['ImageSource'] == 'existing') {
			if(!$data['ExistingFile']) {
				// No image has been selected
				Director::redirectBack();
				return;
			}
			
			$owner->$fieldName = $data['ExistingFile'];

			// Edit the class name, if applicable
			$existingFile = DataObject::get_by_id("File", $data['ExistingFile']);
			$desiredClass = $owner->has_one($data['Field']);
			
			// Unless specifically asked, we don't want the user to be able
			// to select a folder
			if(is_a($existingFile, 'Folder') && $desiredClass != 'Folder') {
				Director::redirectBack();
				return;
			}
			
			if(!is_a($existingFile, $desiredClass)) {
				$existingFile->ClassName = $desiredClass;
				$existingFile->write();
			}
		} else {
			// TODO We need to replace this with a way to get the type of a field
			$imageClass = $owner->has_one($data['Field']);
		
			// If we can't find the relationship, assume its an Image.
			if( !$imageClass) $imageClass = 'Image';	
			
			// Assuming its a decendant of File
			$image = new $imageClass();
			$image->loadUploaded($data['Upload']);
			$owner->$fieldName = $image->ID;
			
		    // store the owner id with the uploaded image
    		$member = Member::currentUser();
			$image->OwnerID = $member->ID;
			$image->write();
		}

		$owner->write();
		Director::redirectBack();
	}

	/**
	 * Delete the image referenced by this form.
	 */
	function delete($data, $form) {
		$owner = DataObject::get_by_id( $data[ 'Class' ], $data[ 'ID' ] );
		$fieldName = $data[ 'Field' ] . 'ID';
		$owner->$fieldName = 0;
		$owner->write();
		Director::redirect($this->Link('iframe'));
	}

	/**
	 * Flush all of the generated images.
	 */
	function flush() {
		if(!Permission::check('ADMIN')) Security::permissionFailure($this);
		
		$images = DataObject::get("Image","");
		$numItems = 0;
		$num = 0;
		
		foreach($images as $image) {
			$numDeleted = $image->deleteFormattedImages();
			if($numDeleted) {
				$numItems++;
			}
			$num += $numDeleted;
		}
		echo $num . ' formatted images from ' . $numItems . ' items flushed';
	}
}

?>
