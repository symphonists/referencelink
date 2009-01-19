<?php

	Class extension_referencelink extends Extension{

		public function about(){
			return array(
				'name' => 'Field: Reference Link',
				'version' => '1.0',
				'release-date' => '2009-01-18',
				'author' => array(
					'name' => 'craig zheng',
					'email' => 'cz@mongrl.com'
				)
			);
		}
		
		public function getSubscribedDelegates() {
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'InitaliseAdminPageHead',
					'callback' => 'initializeAdmin'
				)
			);
		}
		
		public function initializeAdmin($context) {
			$page = $context['parent']->Page;
			$assets_path = '/extensions/referencelink/assets/';
			
			// load jQuery and autocomplete JS
			$page->addScriptToHead('http://ajax.googleapis.com/ajax/libs/jquery/1.2.6/jquery.min.js', 220);
			$page->addScriptToHead(URL . $assets_path . 'referencelink.js', 230);
			
			// load autocomplete styles
			$page->addStylesheetToHead(URL . $assets_path . 'referencelink.css', 'screen', 100);
		}
		
		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_referencelink`");
		}
		
		public function install(){
			return $this->_Parent->Database->query(
				"CREATE TABLE `tbl_fields_referencelink` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`related_field_id` int(11) unsigned default NULL,
					`field_type` enum('select','autocomplete') NOT NULL default 'select',
					`allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) TYPE=MyISAM;"
			);
		}

	}