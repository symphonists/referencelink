<?php

    require_once(EXTENSIONS . "/selectbox_link_field/fields/field.selectbox_link.php");
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldReferenceLink extends fieldSelectBox_Link{

	// FIELD DEFINITION

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->_name = 'Reference Link';
		}

		public function findDefaults(&$fields){
			if(!isset($fields['allow_multiple_selection'])) $fields['allow_multiple_selection'] = 'no';
			if(!isset($fields['field_type'])) $fields['field_type'] = 'select';
		}
		
	// FIELD SETUP & CREATION
		
		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			Field::displaySettingsPanel($wrapper, $errors);
			
			$div = new XMLElement('div', NULL, array('class' => 'group'));
			
			$label = Widget::Label('Options');
			
			$sectionManager = new SectionManager($this->_engine);
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();
			
			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section) $field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
			}
			
			$options = array();
			
			foreach($field_groups as $group){
				
				if(!is_array($group['fields'])) continue;
				
				$fields = array();
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate()){
						$fields[] = array($f->get('id'), in_array($f->get('id'), $this->get('related_field_id')), $f->get('label'));
					}
				}
				
				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id][]', $options, array('multiple' => 'multiple')));
			
			$div->appendChild($label);
			
			// set field type
			$label = Widget::Label('Field Type');
			$type_options = array(array('select', ($this->get('field_type') == 'select'), 'Select Box'), array('autocomplete', ($this->get('field_type') == 'autocomplete'), 'Autocomplete Input'));
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][field_type]', $type_options));
			$div->appendChild($label);
			
			// Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') $input->setAttribute('checked', 'checked');			
			$label->setValue($input->generate() . ' Allow selection of multiple options');
			$div->appendChild($label);
						
			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);
				
			## Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][limit]', $this->get('limit'));
			$input->setAttribute('size', '3');
			$label->setValue('Limit to the ' . $input->generate() . ' most recent entries');
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
			$this->appendRequiredCheckbox($wrapper);
		}
		
		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');
			if($id === false) return false;
			
			// set field instance values
			$fields = array();
			$fields['field_id'] = $id;
			
			if($this->get('related_field_id') != ''){
				$fields['related_field_id'] = $this->get('related_field_id');
			}
			
			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['limit'] = max(1, (int)$this->get('limit'));
            $fields['related_field_id'] = implode(',', $this->get('related_field_id'));
			$fields['field_type'] = ($this->get('field_type') ? $this->get('field_type') : 'select');
			// save/replace field instance
			$this->Database->query("DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '$id'");
			
			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) {
				return false;
			}
			
			// build associations
			$this->removeSectionAssociation($id);
			
			foreach($this->get('related_field_id') as $field_id){
                $this->createSectionAssociation(NULL, $id, $field_id);
            }

			return true;
		}
		
	// ENTRY PUBLISHING
	
		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {
			
			if(!is_array($data['relation_id'])){
				$entry_ids = array($data['relation_id']);
			}
			
			else{
				$entry_ids = array_values($data['relation_id']);
			}
			
			// build list of target entries
			$states = $this->findOptions($entry_ids);
			$options = array();
			
			if($this->get('required') != 'yes') {
				$options[] = array(NULL, false, NULL);
			}
			if($this->get('required') == 'yes') {
				$options[] = array('none', true, 'Choose one');
			}
			
			if(!empty($states)){
				foreach($states as $s){
					$group = array('label' => $s['name'], 'options' => array());
					foreach($s['values'] as $id => $v){
						$group['options'][] = array($id, in_array($id, $entry_ids), $v);
					}
					$options[] = $group;
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

	}