<?php
/**
 * An example custom profile plugin.
 *
 * @package			Joomla.Plugins
 * @subpackage	user.profile
 * @version			1.6
 * @copyright		Copyright (C) 2005 - 2010 Open Source Matters, Inc. All rights reserved.
 * @license			GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_BASE') or die;

class plgUserCustomprofile extends JPlugin
{
	/**
	 * @param	string	The context for the data
	 * @param	int		The user id
	 * @param	object
	 * @return	boolean
	 * @since	1.6
	 */

	function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile','com_users.registration','com_users.user','com_admin.profile'))){
			return true;
		}

		$userId = isset($data->id) ? $data->id : 0;

		// Load the profile data from the database.
		$db = JFactory::getDbo();
		$sql = $db->getQuery(true);
		$sql
			->select('profile_key, profile_value')
			->from($db->qn('#__user_profiles'))
			->where('user_id = '.(int) $userId)
			->where('profile_key LIKE '.$db->q('customprofile.%'))
			->order('ordering ASC');
		$db->setQuery($sql);
		$results = $db->loadRowList();

		// Check for a database error.
		if ($db->getErrorNum()) {
			$this->_subject->setError($db->getErrorMsg());
			return false;
		}

		// Merge the profile data.
		$data->customprofile = array();
		foreach ($results as $v) {
			$k = str_replace('customprofile.', '', $v[0]);
			$data->customprofile[$k] = json_decode($v[1], true);
		}

		return true;
	}

	/**
	 * @param	JForm	The form to be altered.
	 * @param	array	The associated data for the form.
	 * @return	boolean
	 * @since	1.6
	 */
	function onContentPrepareForm($form, $data)
	{
		// Load user_profile plugin language
		$lang = JFactory::getLanguage();
		$lang->load('plg_user_customprofile', JPATH_ADMINISTRATOR);

		if (!($form instanceof JForm)) {
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}
		// Check we are manipulating a valid form.
		if (!in_array($form->getName(), array('com_users.profile', 'com_users.registration','com_users.user','com_admin.profile'))) {
			return true;
		}
		if ($form->getName()=='com_users.profile')
		{
			// Add the profile fields to the form.
			JForm::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);

			// Toggle whether the something field is required.
			if ($this->params->get('profile-require_something', 1) > 0) {
				$form->setFieldAttribute('something', 'required', $this->params->get('profile-require_something') == 2, 'customprofile');
			} else {
				$form->removeField('something', 'customprofile');
			}
		}

		//In this example, we treat the frontend registration and the back end user create or edit as the same. 
		elseif ($form->getName()=='com_users.registration' || $form->getName()=='com_users.user' )
		{		
			// Add the registration fields to the form.
			JForm::addFormPath(dirname(__FILE__).'/profiles');
			$form->loadFile('profile', false);

			// Toggle whether the something field is required.
			if ($this->params->get('register-require_something', 1) > 0) {
				$form->setFieldAttribute('something', 'required', $this->params->get('register-require_something') == 2, 'customprofile');
			} else {
				$form->removeField('something', 'customprofile');
			}
		}			
	}

	function onUserAfterSave($data, $isNew, $result, $error)
	{
		$userId	= Joomla\Utilities\ArrayHelper::getValue($data, 'id', 0, 'int');

		if ($userId && $result && isset($data['customprofile']) && (count($data['customprofile'])))
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery('DELETE FROM #__user_profiles WHERE user_id = '.$userId.' AND profile_key LIKE \'customprofile.%\'');
				if (!$db->execute()) {
					throw new Exception($db->getErrorMsg());
				}

				$tuples = array();
				$order	= 1;
				foreach ($data['customprofile'] as $k => $v) {
					$tuples[] = '('.$userId.', '.$db->quote('customprofile.'.$k).', '.$db->quote(json_encode($v)).', '.$order++.')';
				}

				$db->setQuery('INSERT INTO #__user_profiles VALUES '.implode(', ', $tuples));
				if (!$db->execute()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e) {
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param	array		$user		Holds the user data
	 * @param	boolean		$success	True if user was succesfully stored in the database
	 * @param	string		$msg		Message
	 */
	function onUserAfterDelete($user, $success, $msg)
	{
		if (!$success) {
			return false;
		}

		$userId	= Joomla\Utilities\ArrayHelper::getValue($user, 'id', 0, 'int');

		if ($userId)
		{
			try
			{
				$db = JFactory::getDbo();
				$db->setQuery(
					'DELETE FROM #__user_profiles WHERE user_id = '.$userId .
					" AND profile_key LIKE 'customprofile.%'"
				);

				if (!$db->execute()) {
					throw new Exception($db->getErrorMsg());
				}
			}
			catch (JException $e)
			{
				$this->_subject->setError($e->getMessage());
				return false;
			}
		}

		return true;
	}


}
