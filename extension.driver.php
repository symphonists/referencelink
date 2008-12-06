<?php

	Class extension_referencelink extends Extension{
	
		public function about(){
			return array('name' => 'Field: Reference Link',
						 'version' => '1.0',
						 'release-date' => '2008-12-01',
						 'author' => array('name' => 'craig zheng',
										   'website' => 'http://mongrl.com',
										   'email' => 'craig.zheng@gmail.com')
				 		);
		}

    public function getSubscribedDelegates() {
      return array(
        array(
          'page'    => '/backend/',
          'delegate'  => 'InitaliseAdminPageHead',
          'callback'  => 'initializeAdmin'
        )
      );
    }
    
    public function initializeAdmin($context) {
      $page = $context['parent']->Page;

      $page->addStylesheetToHead(URL . '/extensions/name/assets/autocomplete.css', 'screen', 100);
      
      $context['parent']->Page->addScriptToHead(URL . '/extensions/referencelink/assets/jquery-1.2.6.min.js', 220);

      $context['parent']->Page->addScriptToHead(URL . '/extensions/referencelink/assets/referencelink.js', 230);

    }

		
		public function uninstall(){
			$this->_Parent->Database->query("DROP TABLE `tbl_fields_referencelink`");
		}


		public function install(){

			return $this->_Parent->Database->query("CREATE TABLE `tbl_fields_referencelink` (
		 	  `id` int(11) unsigned NOT NULL auto_increment,
			  `field_id` int(11) unsigned NOT NULL,
			  `allow_multiple_selection` enum('yes','no') NOT NULL default 'no',
        `field_type` enum('select','autocomplete') NOT NULL default 'select',
			  `related_field_id` int(11) unsigned default NULL,
			  PRIMARY KEY  (`id`),
			  KEY `field_id` (`field_id`)
			) TYPE=MyISAM;");

		}
			
	}