<?php

	Class extension_referencelink extends Extension{

		public function about(){
			return array(
				'name' => 'Field: Reference Link',
				'version' => '1.1',
				'release-date' => '2009-02-13',
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
		
		public function update($previousVersion){	
			if(version_compare($previousVersion, '1.2', '<')){
				$this->_Parent->Database->query("ALTER TABLE `tbl_fields_referencelink` ADD `limit` INT(4) UNSIGNED NOT NULL DEFAULT '20'");
				$this->_Parent->Database->query("ALTER TABLE `tbl_fields_referencelink` CHANGE `related_field_id` `related_field_id` VARCHAR(255) NOT NULL");
			}

			return true;
		}
		
		public function install(){
			return $this->_Parent->Database->query(
				"CREATE TABLE `tbl_fields_referencelink` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`related_field_id` varchar(255) NOT NULL,
					`limit` INT(4) UNSIGNED NOT NULL DEFAULT '20',
					`field_type` enum('select','autocomplete') NOT NULL default 'select',
					`allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) TYPE=MyISAM;"
			);
		}

	}