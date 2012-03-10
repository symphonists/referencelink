<?php

	Class extension_referencelink extends Extension{

		public function about(){
			return array(
				'name'			=> 'Field: Reference Link',
				'version'		=> '1.4',
				'release-date'	=> '2011-10-10',
				'author'		=> array(
					'name'			=> 'craig zheng',
					'email'			=> 'craig@symphony-cms.com'
				),
				'description'	=> 'Autocomplete-enabled version of Select Box Link.'
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
			$page = Administration::instance()->Page;
			$assets_path = '/extensions/referencelink/assets/';
			if($page instanceof contentPublish) {
				$page->addScriptToHead(URL . $assets_path . 'referencelink.publish.js', 900);
				$page->addStylesheetToHead(URL . $assets_path . 'referencelink.publish.css', 'screen', 100);
			}
		}

		public function uninstall(){
			Symphony::Database()->query("DROP TABLE `tbl_fields_referencelink`");
		}

		public function update($previousVersion){

			if(version_compare($previousVersion, '1.4', '<')){
				try{
					Symphony::Database()->query("
						ALTER TABLE
							`tbl_fields_referencelink`
						ADD COLUMN
							`show_association` enum('yes','no')
						NOT NULL
							default 'yes'
					");
				}
				catch(Exception $e){
					// Discard
				}
			}

			if(version_compare($previousVersion, '1.3.1', '<')){
				try{
					$fields = Symphony::Database()->fetchCol('field_id',
						"SELECT `field_id` FROM `tbl_fields_referencelink`"
					);
				}
				catch(Exception $e){
					// Discard
				}

				if(is_array($fields) && !empty($fields)){
					foreach($fields as $field_id){
						try{
							Symphony::Database()->query(
								"ALTER TABLE `tbl_entries_data_{$field_id}`
								CHANGE `relation_id` `relation_id` INT(11) UNSIGNED NULL DEFAULT NULL"
							);
						}
						catch(Exception $e){
							// Discard
						}
					}
				}
			}

			if(version_compare($previousVersion, '1.2', '<')){
				Symphony::Database()->query("ALTER TABLE `tbl_fields_referencelink` ADD `limit` INT(4) UNSIGNED NOT NULL DEFAULT '20'");
				Symphony::Database()->query("ALTER TABLE `tbl_fields_referencelink` CHANGE `related_field_id` `related_field_id` VARCHAR(255) NOT NULL");
			}

			return true;
		}

		public function install(){
			return Symphony::Database()->query(
				"CREATE TABLE `tbl_fields_referencelink` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`field_id` int(11) unsigned NOT NULL,
					`related_field_id` varchar(255) NOT NULL,
					`limit` INT(4) UNSIGNED NOT NULL DEFAULT '20',
					`field_type` enum('select','autocomplete') NOT NULL default 'select',
					`allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
					`show_association` enum('yes','no') NOT NULL default 'yes',
					PRIMARY KEY (`id`),
					KEY `field_id` (`field_id`)
				) TYPE=MyISAM;"
			);
		}

	}
