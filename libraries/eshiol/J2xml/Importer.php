<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  eshiol.J2XML
 *
 * @version     23.4.379-rc2
 * @since       1.6.0
 *
 * @author      Helios Ciancio <info (at) eshiol (dot) it>
 * @link        https://www.eshiol.it
 * @copyright   Copyright (C) 2010 - 2023 Helios Ciancio. All Rights Reserved
 * @license     http://www.gnu.org/licenses/gpl-3.0.html GNU/GPL v3
 * J2XML is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License
 * or other free or open source software licenses.
 */
namespace eshiol\J2xml;

// no direct access
defined('_JEXEC') or die('Restricted access.');

use eshiol\J2xml\Table\Category;
use eshiol\J2xml\Table\Contact;
use eshiol\J2xml\Table\Content;
use eshiol\J2xml\Table\Field;
use eshiol\J2xml\Table\Fieldgroup;
use eshiol\J2xml\Table\Image;
use eshiol\J2xml\Table\Menu;
use eshiol\J2xml\Table\Menutype;
use eshiol\J2xml\Table\Module;
use eshiol\J2xml\Table\Tag;
use eshiol\J2xml\Table\User;
use eshiol\J2xml\Table\Usernote;
use eshiol\J2xml\Table\Viewlevel;
use eshiol\J2xml\Table\Weblink;
use eshiol\J2xml\Version;

\JLoader::import('eshiol.J2xml.Table.Category');
\JLoader::import('eshiol.J2xml.Table.Contact');
\JLoader::import('eshiol.J2xml.Table.Content');
\JLoader::import('eshiol.J2xml.Table.Field');
\JLoader::import('eshiol.J2xml.Table.Fieldgroup');
\JLoader::import('eshiol.J2xml.Table.Image');
\JLoader::import('eshiol.J2xml.Table.Menu');
\JLoader::import('eshiol.J2xml.Table.Menutype');
\JLoader::import('eshiol.J2xml.Table.Module');
\JLoader::import('eshiol.J2xml.Table.Tag');
\JLoader::import('eshiol.J2xml.Table.User');
\JLoader::import('eshiol.J2xml.Table.Usernote');
\JLoader::import('eshiol.J2xml.Table.Viewlevel');
\JLoader::import('eshiol.J2xml.Table.Weblink');
\JLoader::import('eshiol.J2xml.Version');

\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_weblinks/tables');
\JTable::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_contact/tables');

// \JLoader::import('joomla.filesystem.folder');
\JLoader::import('joomla.filesystem.file');
\JLoader::import('joomla.user.helper');

/**
 *
 * Importer
 *
 */
class Importer
{

	protected $_nullDate;

	protected $_user_id;

	protected $_now;

	protected $_option;

	protected $_usergroups;

	function __construct ()
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'com_j2xml'));

		$db         = \JFactory::getDbo();
		$version    = new \JVersion();
		$serverType = $version->isCompatible('3.5') ? $db->getServerType() : 'mysql';

		// Merge the default translation with the current translation
		if ($version->isCompatible('3.2'))
		{
			$jlang = \JFactory::getApplication()->getLanguage();
		}
		else
		{
			$jlang = \JFactory::getLanguage();
		}
		$jlang->load('lib_j2xml', JPATH_SITE, 'en-GB', true);
		$jlang->load('lib_j2xml', JPATH_SITE, $jlang->getDefault(), true);
		$jlang->load('lib_j2xml', JPATH_SITE, null, true);

		$this->_user     = \JFactory::getUser();
		$this->_nullDate = $db->getNullDate();
		$this->_user_id  = $this->_user->get('id');
		$this->_now      = \JFactory::getDate()->format("%Y-%m-%d-%H-%M-%S");
		$this->_option   = (PHP_SAPI != 'cli') ? \JFactory::getApplication()->input->getCmd('option') : 'cli_' .
				 strtolower(get_class(\JApplicationCli::getInstance()));

		try {
			$query = "CREATE TABLE IF NOT EXISTS " . $db->quoteName("#__j2xml_usergroups");
			if ($serverType == 'postgresql')
			{
				$query .= " (" .
					$db->quoteName("id") . " serial NOT NULL," .
					$db->quoteName("parent_id") . " bigint DEFAULT 0 NOT NULL," .
					$db->quoteName("title") . " varchar(100) DEFAULT '' NOT NULL," .
					"PRIMARY KEY (" . $db->quoteName("id") . "));";
				$db->setQuery($query)->execute();
			}
			else
			{
				$query .= " (" .
					$db->quoteName("id") . " int(10) unsigned NOT NULL," .
					$db->quoteName("parent_id") . " int(10) unsigned NOT NULL DEFAULT '0'," .
					$db->quoteName("title") . " varchar(100) NOT NULL DEFAULT ''," .
					"PRIMARY KEY (" . $db->quoteName("id") . ")) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci;";
			}
			$db->setQuery($query)->execute();

			$db->truncateTable("#__j2xml_usergroups");

			$query = $db->getQuery(true)
			//	->insert($db->quoteName("#__j2xml_usergroups"))
				->select($db->quoteName("id"))
				->select($db->quoteName("parent_id"))
				->select("CONCAT('[\"',REPLACE(" . $db->quoteName("title") . ",'\"','\\\"'),'\"]')")
				->from($db->quoteName("#__usergroups"));
			$query = "INSERT INTO " . $db->quoteName("#__j2xml_usergroups") . $query;
			$db->setQuery($query)->execute();

			do {
				$query = $db->getQuery(true)
					->update($db->quoteName("#__j2xml_usergroups", "j"))
					->join("INNER", $db->quoteName("#__usergroups", "g"), $db->quoteName("j.parent_id") . " = " . $db->quoteName("g.id"))
					->set($db->quoteName("j.parent_id") . " = " . $db->quoteName("g.parent_id"))
					->set($db->quoteName("j.title") . " = CONCAT('[\"',REPLACE(" . $db->quoteName("g.title") . ",'\"','\\\"'), '\",', SUBSTR(" . $db->quoteName("j.title") . ",2))");
				$db->setQuery($query)->execute();

				$query = $db->getQuery(true)
					->select("COUNT(*)")
					->from($db->quoteName("#__j2xml_usergroups"))
					->where($db->quoteName("parent_id") . " > 0");
				$n = $db->setQuery($query)->loadResult();
			} while ($n > 0);
		}
		catch (\JDatabaseExceptionExecuting $e)
		{
			// If the query fails we will go on
		}

	}

	/**
	 * Import data
	 *
	 * @param \SimpleXMLElement $xml
	 *			xml
	 * @param \JRegistry $options
	 *			An optional associative array of settings.
	 *			@option boolean 'import_content' import articles
	 *			@option int 'default_category'
	 *			@option int 'content_category'
	 *
	 * @throws
	 * @return boolean
	 * @access public
	 *
	 * @since 1.6.0
	 */
	function import ($xml, $params)
	{
		\JLog::add(new \JLogEntry(__METHOD__, \JLog::DEBUG, 'com_j2xml'));

		$import_viewlevels = $params->get('viewlevels');
		if ($import_viewlevels)
		{
			Viewlevel::import($xml, $params);
		}

		$version = new \JVersion();
		if ($version->isCompatible('3.7'))
		{
			$import_fields = $params->get('fields', 0);
			if ($import_fields)
			{
				Fieldgroup::import($xml, $params);
				Field::import($xml, $params);
			}
		}

		$import_users = $params->get('users');
		if ($import_users)
		{
			User::import($xml, $params);
		}

		if ($version->isCompatible('3.1'))
		{
			$import_tags = $params->get('tags', 1);
			if ($import_tags)
			{
				Tag::import($xml, $params);
			}
		}

		$import_content = $params->get('content');
		if ($import_content)
		{
			Content::import($xml, $params);
		}

		$import_images = $params->get('images', 0);
		if ($import_images)
		{
			Image::import($xml, $params);
		}

		$import_usernotes = $params->get('usernotes', 0);
		if ($import_usernotes)
		{
			Usernote::import($xml, $params);
		}

		$import_contacts = $params->get('contacts', 0);
		if ($import_contacts)
		{
			Contact::import($xml, $params);
		}

		$import_weblinks = $params->get('weblinks');
		if ($import_weblinks)
		{
			Weblink::import($xml, $params);
		}

		$import_menus = $params->get('menus', 1);
		if ($import_menus)
		{
			Menutype::import($xml, $params);
			Menu::import($xml, $params);
		}

		$import_modules = $params->get('modules', 1);
		if ($import_modules)
		{
			Module::import($xml, $params);
		}

		if ($params->get('fire', 1))
		{
			\JPluginHelper::importPlugin('j2xml');
			// Trigger the onAfterImport event.
			$results = \JFactory::getApplication()->triggerEvent('onContentAfterImport', array(
				'com_j2xml.import',
				&$xml,
				$params
			));
		}

		return true;
	}

	/**
	 * Return true if the file is supported
	 *
	 * @param String $version
	 *
	 * @return boolean
	 * @since  21.12.353
	 */
	public function isSupported(String $version)
	{
		return in_array($version, ["211200", "190200", "150900", "120500"]);
	}
}
