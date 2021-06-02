<?php
/**
 * @package     Joomla\Component\Content\Api\Model
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Joomla\Component\Content\Api\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Component\Content\Administrator\Model\ArticleModel as AdminArticleModel;
// TODO: replace with Joomla\Component\Media\Api\Helper\MediaHelper
// as soon as media web service PR is approved and merged.
use Joomla\Component\Content\Api\Helper\AdapterTrait;
use Joomla\Component\Content\Api\Helper\MediaHelper;
use Joomla\Component\Media\Administrator\Model\ApiModel;
use Tobscure\JsonApi\Exception\InvalidParameterException;

class ArticleModel extends AdminArticleModel
{
	use AdapterTrait;

	/**
	 * Instance of com_media's ApiModel
	 *
	 * @var ApiModel
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private $mediaApiModel;

	public function __construct($config = []) {
		parent::__construct($config);

		$this->mediaApiModel = new ApiModel();
	}

	/**
	 * Method to save article data.
	 *
	 * @param   array  $data  The form data.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function save($data)
	{
		$imagesContent = $this->getState('images_content', null);

		if (!(isset($imagesContent['intro']) || isset($imagesContent['fulltext'])))
		{
			return parent::save($data);
		}

		foreach ($imagesContent as $type => $imageContent)
		{
			$imageType = 'image_' . $type;

			$result = $this->saveImageContent(
				$imageContent['content'] ?: null,
				$imageContent['path'] ?: '',
				$data['images'][$imageType] ?: '',
				true
			);
			/**
			 * Returns the folders and files for the given path. The returned objects
			 * have the following properties available:
			 * - type:          The type can be file or dir
			 * - name:          The name of the file
			 * - path:          The relative path to the root
			 * - extension:     The file extension
			 * - size:          The size of the file
			 * - create_date:   The date created
			 * - modified_date: The date modified
			 * - mime_type:     The mime type
			 * - width:         The width, when available
			 * - height:        The height, when available
			*/
			$data['images'][$imageType] = $result->path;
		}

		return parent::save($data);
	}

	/**
	 * Method to save a file or folder.
	 *
	 * @param   string  $path  The primary key of the item (if exists)
	 *
	 * @return  string  Url of the saved image.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function saveImageContent($content, $path, $oldPath, $override = false)
	{
		[
			'adapter' => $adapterName,
			'path'    => $path,
		] = MediaHelper::adapterNameAndPath($path);

		$resultPath = '';

		// If we have a (new) path and an old path that are not the same, assume
		// we want to move an existing file or folder.
		if ($path && $oldPath && $path !== $oldPath)
		{
			// ApiModel::move() (or actually LocalAdapter::move()) returns a path
			// with leading slash.
			$resultPath = trim(
				$this->mediaApiModel->move(
					$adapterName, $oldPath, $path, $override
				), '/'
			);
		}

		// If we have a (new) path but no old path, assume we want to create a
		// new file or folder.
		if ($path && !$oldPath)
		{
			// com_media expects separate directory and file name.
			// If we moved the file before, we must use the new path.
			$basename = basename($resultPath ?: $path);
			$dirname  = dirname($resultPath ?: $path);

			// If there is content, com_media's assumes the new item is a file.
			// Otherwise a folder is assumed.
			$name = $content
				? $this->mediaApiModel->createFile(
					$adapterName, $basename, $dirname, $content, $override
				)
				: $this->mediaApiModel->createFolder(
					$adapterName, $basename, $dirname, $override
				);

			$resultPath = $dirname . '/' . $name;
		}

		// If we have no (new) path but we do have an old path and we have content,
		// we want to update the contents of an existing file.
		if ($oldPath && $content)
		{
			// com_media expects separate directory and file name.
			// If we moved the file before, we must use the new path.
			$basename = basename($resultPath ?: $oldPath);
			$dirname  = dirname($resultPath ?: $oldPath);

			$this->mediaApiModel->updateFile(
				$adapterName, $basename, $dirname, $content
			);

			$resultPath = $oldPath;
		}

		$adapter = $this->getAdapter($adapterName);

		return $adapter->getUrl($resultPath);
	}
}
