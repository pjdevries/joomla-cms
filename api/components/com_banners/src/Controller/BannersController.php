<?php
/**
 * @package     Joomla.API
 * @subpackage  com_banners
 *
 * @copyright   (C) 2019 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Banners\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;

/**
 * The banners controller
 *
 * @since  4.0.0
 */
class BannersController extends ApiController
{
	/**
	 * The content type of the item.
	 *
	 * @var    string
	 * @since  4.0.0
	 */
	protected $contentType = 'banners';

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 * @since  3.0
	 */
	protected $default_view = 'banners';

	/**
	 * Query filter parameters => model state mappings
	 *
	 * @var  array
	 */
	protected $queryFilterModelStateMap = [
		'category_id' => [
			'name' => 'filter.category_id',
			'type' => 'INT'
		],
		'client_id' => [
			'name' => 'filter.client_id',
			'type' => 'INT'
		],
		'level' => [
			'name' => 'filter.level',
			'type' => 'INT'
		],
		'search' => [
			'name' => 'filter.search',
			'type' => 'STRING'
		],
		'state' => [
			'name' => 'filter.published',
			'type' => 'INT'
		],
		'language' => [
			'name' => 'filter.language',
			'type' => 'STRING'
		],
	];
}
