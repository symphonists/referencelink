<?php

	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldReferenceLink extends Field{

	// FIELD DEFINITION

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Reference Link';
			$this->_required = true;
			$this->set('show_column', 'no');
			$this->set('required', 'yes');
		}

		public function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
			if(!isset($fields['field_type'])) $fields['field_type'] = 'select';
		}
		
		public function createTable(){
			return $this->_engine->Database->query(
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`entry_id` int(11) unsigned NOT NULL,
					`relation_id` int(11) unsigned NOT NULL,
					PRIMARY KEY (`id`),
					KEY `entry_id` (`entry_id`),
					KEY `relation_id` (`relation_id`)
				) TYPE=MyISAM"
			);
		}
		
		public function canToggle(){
			return ($this->get('allow_multiple_selection') == 'yes' ? false : true);
		}
		
		public function canFilter(){
			return true;
		}
		
		public function allowDatasourceOutputGrouping(){
			return true;
		}
		
		function allowDatasourceParamOutput(){
			return true;
		}
		
	// FIELD SETUP & CREATION
		
		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			parent::displaySettingsPanel($wrapper, $errors);
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			// build sections list
			
			$label = Widget::Label('Target Section(s)');
			$sectionManager = new SectionManager($this->_engine);
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();
			
			if(is_array($sections) && !empty($sections)) {
				foreach($sections as $section) {
					$field_groups[$section->get('id')] = array(
						'fields' => $section->fetchFields(), 
						'section' => $section
					);
				}
			}
			
			$options = array();
			
			foreach($field_groups as $group){
				if(!is_array($group['fields'])) continue;
				$fields = array();
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()) {
						$fields[] = array($f->get('id'), ($this->get('related_field_id') == $f->get('id')), $f->get('label'));
					}
				}
				if(is_array($fields) && !empty($fields)){
					$options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
				}
			}
			
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][related_field_id]', $options));
			$div->appendChild($label);
			
			if(isset($errors['related_field_id'])) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			}
			else {
				$wrapper->appendChild($div);
			}
			
			// set field type
			
			$label = Widget::Label('Field Type');
			$type_options = array(array('select', ($this->get('field_type') == 'select'), 'Select Box'), array('autocomplete', ($this->get('field_type') == 'autocomplete'), 'Autocomplete Input'));
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][field_type]', $type_options));
			$div->appendChild($label);
			
			// set field options
			
			$label = Widget::Label();
			$input = Widget::Input('fields[' . $this->get('sortorder') . '][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue($input->generate() . 'Allow selection of multiple options');
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
			$this->appendRequiredCheckbox($wrapper);
		}
		
		public function commit(){
			if(!parent::commit()) return false;
			
			$id = $this->get('id');
			if($id === false) return false;
			
			// set field instance values
			$fields = array();
			$fields['field_id'] = $id;
			
			if($this->get('related_field_id') != ''){
				$fields['related_field_id'] = $this->get('related_field_id');
			}
			
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			
			$fields['field_type'] = ($this->get('field_type') ? $this->get('field_type') : 'select');
			
			// save/replace field instance
			$this->Database->query("DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '$id'");
			
			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) {
				return false;
			}
			
			// build associations
			$section = $this->get('related_field_id');
			$this->removeSectionAssociation($id);
			
			$section_id = $this->Database->fetchVar('parent_section', 0, "SELECT `parent_section` FROM `tbl_fields` WHERE `id` = '" . $fields['related_field_id'] . "' LIMIT 1");
			
			$this->createSectionAssociation(NULL, $id, $this->get('related_field_id'));

			return true;
		}
		
	// ENTRY PUBLISHING
	
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
			
			// build list of target entries
			$target_data = $this->findOptions();
			$options = array();
			
			if($this->get('required') != 'yes') {
				$options[] = array(NULL, false, NULL);
			}
			if($this->get('required') == 'yes') {
				$options[] = array('none', true, 'Choose one');
			}
			
			if(is_array($data['relation_id'])) {
				foreach($target_data as $id => $name) {
					if (in_array($id, $data['relation_id'])) {
						$options[] = array($id, TRUE, $name);
					}
					else {
						$options[] = array($id, FALSE, $name);
					}
				}
			}
			else {
				foreach($target_data as $id => $name) {
					$options[] = array($id, $id == $data['relation_id'], $name);
				}
			}
			
			// build label and input html
			$fieldname = 'fields' . $fieldnamePrefix . '[' . $this->get('element_name') . ']' . $fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';
						
			$html_attributes = array();
			if($this->get('allow_multiple_selection') == 'yes') {
				$html_attributes['multiple'] = 'multiple';
			}
			if($this->get('field_type') == 'autocomplete') {
				$html_attributes['class'] = 'replace';
			}
			$html_attributes['id'] = $fieldname;
			
			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') $label->appendChild(new XMLElement('i', 'Optional'));
			$label->appendChild(Widget::Select($fieldname, $options, $html_attributes));
			
			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			}
			else {
				$wrapper->appendChild($label);
			}
		}
		
		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
			$status = self::__OK__;
			
			if($this->get('field_type') == 'autocomplete') {
				if($this->get('allow_multiple_selection') == 'yes') {
					$list = $data[0];
					$ids = explode(", ", $list);
					foreach($ids as $id) {
						if($id != '') {
							$result['relation_id'][] = $id;
						}
					}
					return $result;
				}
				else {
					return array('relation_id' => $data);
				}
			}
			else {
				if(!is_array($data)) return array('relation_id' => $data);
				if(empty($data)) return NULL;
				$result = array();
				foreach($data as $a => $value) {
					$result['relation_id'][] = $data[$a];
				}
				
				return $result;
			}
		}
		
		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;
			
			if($this->get('required') == 'yes' && strlen($data) == 0){
				$message = 'This is a required field.';
				return self::__MISSING_FIELDS__;
			}
						
			return self::__OK__;		
		}
		
	// FIELD OUTPUT
	
		public function appendFormattedElement(&$wrapper, $data, $encode=false) {
			if(!is_array($data) || empty($data)) return;
			
			$list = new XMLElement($this->get('element_name'));
			
			if (!is_array($data['relation_id'])) {
				$data['relation_id'] = array($data['relation_id']);
			}
			
			foreach($data['relation_id'] as $value) {
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($value);
				$section = $this->_engine->Database->fetchRow(0, "SELECT `id`, `handle` FROM `tbl_sections` WHERE `id` = '" . $primary_field['parent_section'] . "' LIMIT 1");
				
				$item_handle = Lang::createHandle($primary_field['value']);
				
				$list->appendChild(new XMLElement('item', ($encode ? General::sanitize($value) : $primary_field['value']), array('handle' => $item_handle, 'id' => $value)));
			}
			
			$wrapper->appendChild($list);
		}
		
		function prepareTableValue($data, XMLElement $link=NULL){
			$result = array();
			
			if(!is_array($data) || (is_array($data) && !isset($data['relation_id']))) return parent::prepareTableValue(NULL);
			
			if(!is_array($data['relation_id'])){
				$data['relation_id'] = array($data['relation_id']);
			}
				
			foreach($data['relation_id'] as $relation_id){
				if((int)$relation_id <= 0) continue;
				
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($relation_id);
				
				if(!is_array($primary_field) || empty($primary_field)){
					continue;
				}
				
				$result[$relation_id] = $primary_field;
				
			}
			
			if(!is_null($link)){
				$label = NULL;
				foreach($result as $item){
					$label .= ' ' . $item['value'];
				}
				$link->setValue(General::sanitize(trim($label)));
				return $link->generate();
			}
			
			$output = NULL;

			foreach($result as $relation_id => $item){
				$link = Widget::Anchor($item['value'], sprintf('%s/symphony/publish/%s/edit/%d/', URL, $item['section_handle'], $relation_id));
					
				$output .= $link->generate() . ' ';
			}
			
			return trim($output);
		}

	// MANAGING ASSOCIATIONS
	
		private function __findPrimaryFieldValueFromRelationID($id) {

			$primary_field = $this->Database->fetchRow(0,
				"SELECT `f`.*, `s`.handle AS `section_handle`
				 FROM `tbl_fields` AS `f`
				 INNER JOIN `tbl_sections` AS `s` ON `s`.id = `f`.parent_section
				 WHERE `f`.id = '".$this->get('related_field_id')."'
				 ORDER BY `f`.sortorder ASC "
			);
			if(!$primary_field) return NULL;

			$field = $this->_Parent->create($primary_field['type']);

			$data = $this->Database->fetchRow(0, 
				"SELECT *
				 FROM `tbl_entries_data_".$this->get('related_field_id')."`
				 WHERE `entry_id` = '$id' ORDER BY `id` DESC"
			);
			if(empty($data)) return NULL;

			$primary_field['value'] = $field->prepareTableValue($data);	

			return $primary_field;
		}
		
		public function fetchAssociatedEntrySearchValue($data) {
			if(!is_array($data)) return $data;

			// need to update this to search by id ?

			$searchvalue = $this->_engine->Database->fetchRow(0, 
				"SELECT
					`entry_id`
				FROM
					`tbl_entries_data_".$this->get('related_field_id')."`
				WHERE 
					`handle` = '".addslashes($data['handle'])."' LIMIT 1"
			);

			return $searchvalue['entry_id'];
		}
		
		public function fetchAssociatedEntryCount($value) {
			return $this->_engine->Database->fetchVar('count', 0, 
				"SELECT
					count(*) AS `count`
				FROM
					`tbl_entries_data_".$this->get('id')."`
				WHERE 
					`relation_id` = '$value'"
			);
		}

		public function fetchAssociatedEntryIDs($value) {
			return $this->_engine->Database->fetchCol('entry_id', 
				"SELECT 
					`entry_id` 
				FROM 
					`tbl_entries_data_".$this->get('id')."` 
				WHERE 
					`relation_id` = '$value'"
			);
		}

		public function getParameterPoolValue($data){
			return $data['relation_id'];
		}
		
		public function findOptions() {
			$values = array();

			$sql = 
				"SELECT DISTINCT 
					`value`, `entry_id` 
				FROM 
					`tbl_entries_data_".$this->get('related_field_id')."`
				ORDER BY `value` 
				DESC";

			if($results = $this->Database->fetch($sql)) {
				foreach($results as $r) {
					$value = $this->__findPrimaryFieldValueFromRelationID($r['entry_id']);
					$values[$r['entry_id']] = $value['value'];
				}
			}

			return $values;
		}
		
	// FIELD FILTERING
	
		public function buildDSRetrivalSQL($data, &$joins, &$where, $andOperation=false) {

			$field_id = $this->get('id');

			if($andOperation):

				foreach($data as $key => $bit){
					$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id$key` ON (`e`.`id` = `t$field_id$key`.entry_id) ";
					$where .= " AND `t$field_id$key`.relation_id = '$bit' ";
				}

			else:

				$joins .= " LEFT JOIN `tbl_entries_data_$field_id` AS `t$field_id` ON (`e`.`id` = `t$field_id`.entry_id) ";
				$where .= " AND `t$field_id`.relation_id IN ('".@implode("', '", $data)."') ";

			endif;

			return true;
		}
		
	// FIELD SORTING
	
		public function buildSortingSQL(&$joins, &$where, &$sort, $order='ASC') {
			$joins .= "INNER JOIN `tbl_entries_data_".$this->get('id')."` AS `ed` ON (`e`.`id` = `ed`.`entry_id`) ";
			$sort = 'ORDER BY ' . (strtolower($order) == 'random' ? 'RAND()' : "`ed`.`relation_id` $order");
		}
		
	// FIELD GROUPING
	
		public function groupRecords($records) {
			if(!is_array($records) || empty($records)) return;

			$groups = array($this->get('element_name') => array());

			foreach($records as $r){
				$data = $r->getData($this->get('id'));
				$value = $data['relation_id'];
				$primary_field = $this->__findPrimaryFieldValueFromRelationID($data['relation_id']);

				if(!isset($groups[$this->get('element_name')][$value])) {
					$groups[$this->get('element_name')][$value] = array(
						'attr' => array(
							'link-id' => $data['relation_id'],
							'link-handle' => Lang::createHandle($primary_field['value'])
							),
						'records' => array(), 
						'groups' => array()
					);	
				}	
				$groups[$this->get('element_name')][$value]['records'][] = $r;
			}
			return $groups;
		}

	}