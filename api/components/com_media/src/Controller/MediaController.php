<?php
/**
 * @package         Joomla.API
 * @subpackage      com_media
 *
 * @copyright   (C) 2021 Open Source Matters, Inc. <https://www.joomla.org>
 * @license         GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Media\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\Component\Media\Administrator\Exception\FileExistsException;
use Joomla\Component\Media\Administrator\Exception\InvalidPathException;
use Joomla\Component\Media\Api\Helper\AdapterTrait;
use Joomla\Component\Media\Api\Helper\MediaHelper;
use Joomla\String\Inflector;
use Tobscure\JsonApi\Exception\InvalidParameterException;

/**
 * Media web service controller.
 *
 * @since  __DEPLOY_VERSION__
 */
class MediaController extends ApiController
{
	use AdapterTrait;

	/**
	 * The content type of the item.
	 *
	 * @var    string
	 * @since  __DEPLOY_VERSION__
	 */
	protected $contentType = 'media';

	/**
	 * Query parameters => model state mappings
	 *
	 * @var  array
	 */
	private static $listQueryModelStateMap = [
		'path'    => [
			'name' => 'path',
			'type' => 'STRING'
		],
		'url'     => [
			'name' => 'url',
			'type' => 'BOOLEAN'
		],
		'temp'    => [
			'name' => 'temp',
			'type' => 'BOOLEAN'
		],
		'content' => [
			'name' => 'content',
			'type' => 'BOOLEAN'
		],
	];

	private static $itemQueryModelStateMap = [
		'path'    => [
			'name' => 'path',
			'type' => 'STRING'
		],
		'url'     => [
			'name' => 'url',
			'type' => 'BOOLEAN'
		],
		'temp'    => [
			'name' => 'temp',
			'type' => 'BOOLEAN'
		],
		'content' => [
			'name' => 'content',
			'type' => 'BOOLEAN'
		],
	];

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $default_view = 'media';

	/**
	 * Display a list of files and/or folders.
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function displayList()
	{
		// Set list specific request parameters in model state.
		$this->setModelState(self::$listQueryModelStateMap);

		// Display files in specific path.
		if ($this->input->exists('path'))
		{
			$this->modelState->set('path', $this->input->get('path', '', 'STRING'));
		}

		// Return files (not folders) as url's.
		if ($this->input->exists('url'))
		{
			$this->modelState->set('url', $this->input->get('url', true, 'BOOLEAN'));
		}

		// Map JSON:API compliant filter[search] to com_media model state.
		$apiFilterInfo = $this->input->get('filter', [], 'array');
		$filter        = InputFilter::getInstance();

		// Search for files matching (part of) a name or glob pattern.
		if ($doSearch = array_key_exists('search', $apiFilterInfo))
		{
			$this->modelState->set('search', $filter->clean($apiFilterInfo['search'], 'STRING'));
			// Tell model to search recursively
			$this->modelState->set('search_recursive', $this->input->get('search_recursive', false, 'BOOLEAN'));
		}

		return parent::displayList();
	}

	/**
	 * Display one specific file or folder.
	 *
	 * @param   string  $path  The path of the file to display. Leave empty if you want to retrieve data from the request.
	 *
	 * @return  static  A \JControllerLegacy object to support chaining.
	 *
	 * @throws  InvalidPathException
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function displayItem($path = '')
	{
		// Set list specific request parameters in model state.
		$this->setModelState(self::$itemQueryModelStateMap);

		// Display files in specific path.
		$this->modelState->set('path', $path ?: $this->input->get('path', '', 'STRING'));

		// Return files (not folders) as url's.
		if ($this->input->exists('url'))
		{
			$this->modelState->set('url', $this->input->get('url', true, 'BOOLEAN'));
		}

		return parent::displayItem();
	}

	/**
	 * Set model state using a list of mappings between query parameters and model state names.
	 *
	 * @param   array  $mappings  A list of mappings between query parameters and model state names..
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function setModelState(array $mappings)
	{
		foreach ($mappings as $queryName => $modelState)
		{
			if ($this->input->exists($queryName))
			{
				$this->modelState->set($modelState['name'], $this->input->get($queryName, '', $modelState['type']));
			}
		}
	}

	/**
	 * Method to add a new file or folder.
	 *
	 * @return  void
	 *
	 * @throws  FileExistsException
	 * @throws  InvalidPathException
	 * @throws  \RuntimeException
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 * @since   __DEPLOY_VERSION__
	 */
	public function add()
	{
		$path = $this->input->json->get('path', '', 'STRING');
		$content = $this->input->json->get('content', '', 'RAW');

		$missingParameters = [];

		if (empty($path))
		{
			$missingParameters[] = 'path';
		}

		if (empty($content))
		{
			$missingParameters[] = 'content';
		}

		if (count($missingParameters))
		{
			throw new InvalidParameterException(
				Text::sprintf('WEBSERVICE_COM_MEDIA_MISSING_REQUIRED_PARAMETERS', implode(' & ', $missingParameters))
			);
		}

		$this->modelState->set('path', $this->input->json->get('path', '', 'STRING'));
		// Check if an existing file may be overwritten. Defaults to false.
		$this->modelState->set('override', $this->input->json->get('override', false));

		parent::add();
	}

	/**
	 * Method to check if it's allowed to add a new file or folder
	 *
	 * @param   array  $data  An array of input data.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function allowAdd($data = array())
	{
		$user = $this->app->getIdentity();

		return $user->authorise('core.create', 'com_media');
	}

	/**
	 * Method to modify an existing file or folder.
	 *
	 * @return  void
	 *
	 * @throws  FileExistsException
	 * @throws  InvalidPathException
	 * @throws  \RuntimeException
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function edit()
	{
		// Access check.
		if (!$this->allowEdit())
		{
			throw new NotAllowed('JLIB_APPLICATION_ERROR_CREATE_RECORD_NOT_PERMITTED', 403);
		}

		$path = $this->input->json->get('path', '', 'STRING');
		$content = $this->input->json->get('content', '', 'RAW');

		if (empty($path) && empty($content))
		{
			throw new InvalidParameterException(
				Text::sprintf('WEBSERVICE_COM_MEDIA_MISSING_REQUIRED_PARAMETERS', 'path | content')
			);
		}

		$this->modelState->set('path', $this->input->json->get('path', '', 'STRING'));
		// For renaming/moving files, we need the path to the existing file or folder.
		$this->modelState->set('old_path', $this->input->get('path', '', 'STRING'));
		// Check if an existing file may be overwritten. Defaults to true.
		$this->modelState->set('override', $this->input->json->get('override', false));

		$recordId = $this->save();

		$this->displayItem($recordId);
	}

	/**
	 * Method to check if it's allowed to modify an existing file or folder.
	 *
	 * @param   array  $data  An array of input data.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function allowEdit($data = array(), $key = 'id')
	{
		$user = $this->app->getIdentity();

		// com_media's access rules contains no specific update rule.
		return $user->authorise('core.create', 'com_media');
	}

	/**
	 * Method to create or modify a file or folder.
	 *
	 * @param   integer  $recordKey  The primary key of the item (if exists)
	 *
	 * @return  integer  The record ID on success, false on failure
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function save($recordKey = null)
	{
		// Explicitly get the single item model name.
		$modelName = $this->input->get('model', Inflector::singularize($this->contentType));
		$model     = $this->getModel($modelName, '', ['ignore_request' => true, 'state' => $this->modelState]);

		$json = $this->input->json;

		// Split destination path into adapter name and file path.
		['adapter' => $adapter, 'path' => $path] = MediaHelper::adapterNameAndPath($this->input->get('path', '', 'STRING'));

		// Decode content, if any
		if ($content = base64_decode($json->get('content', '', 'raw')))
		{
			$this->checkContent();
		}

		// If there is no content, com_media's assumes the path refers to a folder.
		$this->modelState->set('content', $content);

		return $model->save();
	}

	/**
	 * Performs various checks to see if it is allowed to save the content.
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function checkContent()
	{
		$params       = ComponentHelper::getParams('com_media');
		$helper       = new \Joomla\CMS\Helper\MediaHelper();
		$serverlength = $this->input->server->getInt('CONTENT_LENGTH');

		// Check if the size of the request body does not exceed various server imposed limits.
		if (($params->get('upload_maxsize', 0) > 0 && $serverlength > ($params->get('upload_maxsize', 0) * 1024 * 1024))
			|| $serverlength > $helper->toBytes(ini_get('upload_max_filesize'))
			|| $serverlength > $helper->toBytes(ini_get('post_max_size'))
			|| $serverlength > $helper->toBytes(ini_get('memory_limit')))
		{
			throw new \RuntimeException(Text::_('COM_MEDIA_ERROR_WARNFILETOOLARGE'), 400);
		}
	}

	/**
	 * Method to delete an existing file or folder.
	 *
	 * @return  void
	 *
	 * @throws  InvalidPathException
	 * @throws  \RuntimeException
	 * @throws  \Exception
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function delete($id = null)
	{
		if (!$this->allowDelete())
		{
			throw new NotAllowed('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED', 403);
		}

		$this->modelState->set('path', $this->input->get('path', '', 'STRING'));

		$modelName = $this->input->get('model', Inflector::singularize($this->contentType));
		$model     = $this->getModel($modelName, '', ['ignore_request' => true, 'state' => $this->modelState]);

		$model->delete();

		$this->app->setHeader('status', 204);
	}

	/**
	 * Method to check if it's allowed to delete an existing file or folder.
	 *
	 * @return  boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function allowDelete()
	{
		$user = $this->app->getIdentity();

		return $user->authorise('core.delete', 'com_media');
	}
}
