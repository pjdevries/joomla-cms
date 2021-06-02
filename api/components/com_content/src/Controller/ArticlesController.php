<?php
/**
 * @package     Joomla.API
 * @subpackage  com_content
 *
 * @copyright   (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Content\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Component\Media\Api\Helper\MediaHelper;
use Joomla\String\Inflector;

/**
 * The article controller
 *
 * @since  4.0.0
 */
class ArticlesController extends ApiController
{

	/**
	 * The content type of the item.
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $contentType = 'articles';

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 * @since  3.0
	 */
	protected $default_view = 'articles';

	/**
	 * Article list view amended to add filtering of data
	 *
	 * @return  static  A BaseController object to support chaining.
	 *
	 * @since   4.0.0
	 */
	public function displayList()
	{
		$apiFilterInfo = $this->input->get('filter', [], 'array');
		$filter        = InputFilter::getInstance();

		if (array_key_exists('author', $apiFilterInfo))
		{
			$this->modelState->set(
				'filter.author_id',
				$filter->clean($apiFilterInfo['author'], 'INT')
			);
		}

		if (array_key_exists('category', $apiFilterInfo))
		{
			$this->modelState->set(
				'filter.category_id',
				$filter->clean($apiFilterInfo['category'], 'INT')
			);
		}

		if (array_key_exists('search', $apiFilterInfo))
		{
			$this->modelState->set(
				'filter.search',
				$filter->clean($apiFilterInfo['search'], 'STRING')
			);
		}

		if (array_key_exists('state', $apiFilterInfo))
		{
			$this->modelState->set(
				'filter.published',
				$filter->clean($apiFilterInfo['state'], 'INT')
			);
		}

		if (array_key_exists('language', $apiFilterInfo))
		{
			$this->modelState->set(
				'filter.language',
				$filter->clean($apiFilterInfo['language'], 'STRING')
			);
		}

		return parent::displayList();
	}

	/**
	 * Method to allow extended classes to manipulate the data to be saved for an extension.
	 *
	 * @param   array  $data  An array of input data.
	 *
	 * @return  array
	 *
	 * @since   4.0.0
	 */
	protected function preprocessSaveData(array $data): array
	{
		foreach (FieldsHelper::getFields('com_content.article') as $field)
		{
			if (isset($data[$field->name]))
			{
				!isset($data['com_fields']) && $data['com_fields'] = [];

				$data['com_fields'][$field->name] = $data[$field->name];
				unset($data[$field->name]);
			}
		}

		return $data;
	}

	/**
	 * Method to save pass additional request data to the model.
	 *
	 * @param   integer  $recordKey  The primary key of the item (if exists)
	 *
	 * @return  integer  The record ID on success, false on failure
	 *
	 * @since   4.0.0
	 */
	protected function save($recordKey = null)
	{
		$jsonData = $this->input->get(
			'data',
			json_decode($this->input->json->getRaw(), true),
			'array');

		$imagesContent = [];

		if (array_key_exists('image_intro_content', $jsonData))
		{
			$imagesContent['intro'] = $jsonData['image_intro_content'];
		}

		if (array_key_exists('image_fulltext_content', $jsonData))
		{
			$imagesContent['fulltext'] = $jsonData['image_fulltext_content'];
		}

		foreach ($imagesContent as &$imageContent)
		{
			if ($imageContent['content'] ?? '')
			{
				$this->checkContent();
				$imageContent['content'] = base64_decode($imageContent['content'] ?? '');
			}
		}

		$this->modelState->set('images_content', $imagesContent);

		return parent::save($recordKey);
	}

	/**
	 * Performs various checks to see if it is allowed to save the content.
	 *
	 * @return  void
	 *
	 * @throws  \RuntimeException
	 *
	 * @since   4.0.0
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

	public function getModel($name = '', $prefix = '', $config = [])
	{
		if (isset($config['state']))
		{
			$config['state']->setProperties($this->modelState->getProperties());
		}
		else
		{
			$config['state'] = $this->modelState;
		}

		return parent::getModel($name, $prefix, $config);
	}
}
