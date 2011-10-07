<?php

    require_once(EXTENSIONS . "/selectbox_link_field/fields/field.selectbox_link.php");
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldReferenceLink extends fieldSelectBox_Link {

	// FIELD DEFINITION

		public function __construct(&$parent) {
			parent::__construct($parent);
			$this->_name = 'Reference Link';
		}

		public function findDefaults(&$fields) {
			if(!isset($fields['allow_multiple_selection'])) {
				$fields['allow_multiple_selection'] = 'no';
			}
			if(!isset($fields['field_type'])) {
				$fields['field_type'] = 'select';
			}
			if(!isset($fields['show_association'])) {
				$fields['show_association'] = 'yes';
			}
		}

	// FIELD SETUP & CREATION

		public function displaySettingsPanel(&$wrapper, $errors=NULL){
			Field::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'group'));

			$label = Widget::Label(__('Values'));

			$sectionManager = new SectionManager($this->_engine);
		  	$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$field_groups = array();

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $section) {
					$field_groups[$section->get('id')] = array('fields' => $section->fetchFields(), 'section' => $section);
				}
			}

			$options = array();

			foreach($field_groups as $group){

				if(!is_array($group['fields'])) continue;

				$fields = array();
				
				foreach($group['fields'] as $f){
					if($f->get('id') != $this->get('id') && $f->canPrePopulate() && !is_null($this->get('related_field_id'))){
						$fields[] = array($f->get('id'), in_array($f->get('id'), $this->get('related_field_id')), $f->get('label'));
					}
				}

				if(is_array($fields) && !empty($fields)) $options[] = array('label' => $group['section']->get('name'), 'options' => $fields);
			}

			$label->appendChild(Widget::Select('fields['.$this->get('sortorder').'][related_field_id][]', $options, array('multiple' => 'multiple')));

			$div->appendChild($label);

			// set field type
			$label = Widget::Label(__('Field Type'));
			$type_options = array(array('select', ($this->get('field_type') == 'select'), __('Select Box')), array('autocomplete', ($this->get('field_type') == 'autocomplete'), __('Autocomplete Input')));
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][field_type]', $type_options));
			$div->appendChild($label);

			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::wrapFormElementWithError($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);

			## Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][limit]', $this->get('limit'));
			$input->setAttribute('size', '3');
			$label->setValue(__('Limit to the %s most recent entries (Select Box only)',array($input->generate())));
			$wrapper->appendChild($label);
			
			// Allow selection of multiple items
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));
			
			$div = new XMLElement('div', NULL, array('class' => 'compact'));
			$div->appendChild($label);
			$this->appendShowAssociationCheckbox($div);
			$this->appendRequiredCheckbox($div);
			$this->appendShowColumnCheckbox($div);
			$wrapper->appendChild($div);
		}

		public function commit(){
			if(!Field::commit()) return false;

			$id = $this->get('id');
			if($id === false) return false;

			// set field instance values
			$fields = array();
			$fields['field_id'] = $id;

			if(!is_null($this->get('related_field_id'))){
				$fields['related_field_id'] = implode(',', $this->get('related_field_id'));
			}

			$fields['allow_multiple_selection'] = ($this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no');
			$fields['show_association'] = $this->get('show_association') == 'yes' ? 'yes' : 'no';
			$fields['limit'] = max(1, (int)$this->get('limit'));
			$fields['field_type'] = ($this->get('field_type') ? $this->get('field_type') : 'select');
			// save/replace field instance
			$this->Database->query("DELETE FROM `tbl_fields_" . $this->handle() . "` WHERE `field_id` = '$id'");

			if(!$this->Database->insert($fields, 'tbl_fields_' . $this->handle())) {
				return false;
			}

			// build associations
			$this->removeSectionAssociation($id);

			if(!is_null($this->get('related_field_id'))){
				foreach($this->get('related_field_id') as $field_id){
					$this->createSectionAssociation(
						NULL,
						$id,
						$field_id,
						$this->get('show_association') == 'yes' ? true : false
					);
				}
			}

			return true;
		}

	// ENTRY PUBLISHING

		public function displayPublishPanel(&$wrapper, $data=NULL, $flagWithError=NULL, $fieldnamePrefix=NULL, $fieldnamePostfix=NULL) {

			$entry_ids = array();

			if(!is_null($data['relation_id'])){
				if(!is_array($data['relation_id'])){
					$entry_ids = array($data['relation_id']);
				}
				else{
					$entry_ids = array_values($data['relation_id']);
				}
			}

			// build label and fieldname
			$label = Widget::Label($this->get('label'));
			$fieldname = 'fields' . $fieldnamePrefix . '[' . $this->get('element_name') . ']' . $fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			// build list of target entries
			$states = $this->findOptions($entry_ids);

			// If this is an autocomplete, add text inputs
			if($this->get('field_type') == 'autocomplete') {

				$entries_list = array();
				foreach($states as $s) {
					foreach($s['values'] as $i => $v) {
						// Reverse the key/value so we can do an easy array_intersect below
						$entries_list[$v] = $i;
					}
				}

				$selected_list = array_intersect($entries_list, $entry_ids);

				$em = new XMLElement('em', __('(Type for suggestions)'));
				$label->appendChild($em);

				$label->appendChild(Widget::Input(
					'search' . $this->get('id'),
					null,
					'text',
					array(
						'id'		=> 'reflink_search' . $this->get('id'),
						'class' 	=> 'reflink_search',
						'multi' 	=> $this->get('allow_multiple_selection'),
						'fields' 	=> implode(',', $this->get('related_field_id'))
					)
				));
				$label->appendChild(Widget::Input(
					$fieldname,
					null,
					'hidden',
					array(
						'id'	=> 'reflink_input' . $this->get('id'),
						'class' => 'reflink_input',
						'value' => implode(',', $entry_ids)
					)
				));

				$ul = new XMLElement('ul', null, array(
					'id' => 'reflink_selections' . $this->get('id'),
					'class' => 'reflink_list'
				));
				foreach($selected_list as $name => $id){
					$li = new XMLElement('li', $name, array('id' => $id, 'class' => $name));
					$a = Widget::Anchor(__('Remove'), '#', __('Remove'), 'deselect', $id);
					$li->appendChild($a);
					$ul->appendChild($li);
				}
				$label->appendChild($ul);
			}
			// Otherwise, add the select box
			else {

				$options = array();

				if($this->get('allow_multiple_selection') != 'yes') {
					if($this->get('required') == 'yes') {
						$options[] = array('none', empty($entry_ids), __('Choose one'));
					}

					if($this->get('required') == 'no') {
						$options[] = array(NULL, false, __('None'));
					}
				}

				if(!empty($states)){
					foreach($states as $s){
						$group = array('label' => $s['name'], 'options' => array());
						foreach($s['values'] as $id => $v){
							$group['options'][] = array($id, in_array($id, $entry_ids), General::sanitize($v));
						}
						$options[] = $group;
					}
				}

				$html_attributes = array();
				if($this->get('allow_multiple_selection') == 'yes') {
					$html_attributes['multiple'] = 'multiple';
				}
				$html_attributes['id'] = $fieldname;

				
				if($this->get('required') != 'yes') {
					$label->appendChild(new XMLElement('i', __('Optional')));
				}
				$label->appendChild(Widget::Select($fieldname, $options, $html_attributes));

			}

			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::wrapFormElementWithError($label, $flagWithError));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

		public function processRawFieldData($data, &$status, $simulate=false, $entry_id=NULL) {
			$status = self::__OK__;

			if(!is_array($data)) {
				if(strrpos($data, ', ') !== false && strrpos($data, ', ') == strlen($data)-2) { // broken comma
					return array('relation_id' => substr_replace($data, '', strrpos($data, ', '), strlen($data)));
				}
				else { // clean value
					return array('relation_id' => $data);
				}
			} 

			if(empty($data)) return NULL;

			$result = array();

			if($this->get('field_type') == 'autocomplete') {
				if($this->get('allow_multiple_selection') == 'yes' && count($data) == 1 && strstr($data[0],',')) {
					$ids = explode(', ', $data[0]);
					foreach($ids as $id) {
						if($id != '') {
							$result['relation_id'][] = $id;
						}
					}
				}
				else {
					foreach($data as $a => $value) {
						$result['relation_id'][] = $data[$a];
					}
				}
			}
			else {
				foreach($data as $a => $value) {
					$result['relation_id'][] = $data[$a];
				}
			}
			return $result;

		}

		public function checkPostFieldData($data, &$message, $entry_id=NULL){
			$message = NULL;

			if(is_array($data)){
				$data = implode('', $data);
			};

			if ($this->get('required') == 'yes' && ($data == '' || $data == 'none' || is_null($data))){
				$message = __("'%s' is a required field.", array($this->get('label')));

				return self::__MISSING_FIELDS__;
			}

			return self::__OK__;
		}

	}
