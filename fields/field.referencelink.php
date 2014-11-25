<?php

    require_once(EXTENSIONS . "/selectbox_link_field/fields/field.selectbox_link.php");
	if(!defined('__IN_SYMPHONY__')) die('<h2>Symphony Error</h2><p>You cannot directly access this file</p>');

	Class fieldReferenceLink extends fieldSelectBox_Link {

		public function __construct() {
			parent::__construct();
			$this->_name = 'Reference Link';
		}

	/*-------------------------------------------------------------------------
		Setup:
	-------------------------------------------------------------------------*/

	/*-------------------------------------------------------------------------
		Settings:
	-------------------------------------------------------------------------*/

		public function findDefaults(array &$settings) {
			if(!isset($settings['allow_multiple_selection'])) {
				$settings['allow_multiple_selection'] = 'no';
			}
			if(!isset($settings['field_type'])) {
				$settings['field_type'] = 'select';
			}
			if(!isset($settings['show_association'])) {
				$settings['show_association'] = 'yes';
			}
		}

		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null){
			Field::displaySettingsPanel($wrapper, $errors);

			$div = new XMLElement('div', NULL, array('class' => 'two columns'));

			$label = Widget::Label(__('Values'));
			$label->setAttribute('class', 'column');
			$sections = SectionManager::fetch(NULL, 'ASC', 'name');
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
			$label->setAttribute('class', 'column');
			$type_options = array(array('select', ($this->get('field_type') == 'select'), __('Select Box')), array('autocomplete', ($this->get('field_type') == 'autocomplete'), __('Autocomplete Input')));
			$label->appendChild(Widget::Select('fields[' . $this->get('sortorder') . '][field_type]', $type_options));
			$div->appendChild($label);

			if(isset($errors['related_field_id'])) $wrapper->appendChild(Widget::Error($div, $errors['related_field_id']));
			else $wrapper->appendChild($div);

			// Maximum entries
			$label = Widget::Label();
			$input = Widget::Input('fields['.$this->get('sortorder').'][limit]', (string)$this->get('limit'));
			$input->setAttribute('size', '3');
			$label->setValue(__('Limit to the %s most recent entries',array($input->generate())));
			$wrapper->appendChild($label);

			// Allow selection of multiple items
			$div = new XMLElement('div', NULL, array('class' => 'two columns'));
			$label = Widget::Label();
			$label->setAttribute('class', 'column');
			$input = Widget::Input('fields['.$this->get('sortorder').'][allow_multiple_selection]', 'yes', 'checkbox');
			if($this->get('allow_multiple_selection') == 'yes') {
				$input->setAttribute('checked', 'checked');
			}
			$label->setValue($input->generate() . ' ' . __('Allow selection of multiple options'));
			$div->appendChild($label);
			$this->appendShowAssociationCheckbox($div);
			$wrapper->appendChild($div);

			$div = new XMLElement('div', NULL, array('class' => 'two columns'));
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

			$fields['allow_multiple_selection'] = $this->get('allow_multiple_selection') ? $this->get('allow_multiple_selection') : 'no';
			$fields['show_association'] = $this->get('show_association') == 'yes' ? 'yes' : 'no';
			$fields['limit'] = max(1, (int)$this->get('limit'));
			$fields['field_type'] = ($this->get('field_type') ? $this->get('field_type') : 'select');

			// save/replace field instance
			if(!FieldManager::saveSettings($id, $fields)) { return false; }

			// build associations
			SectionManager::removeSectionAssociation($id);
			foreach($this->get('related_field_id') as $field_id){
				SectionManager::createSectionAssociation(NULL, $id, $field_id, $this->get('show_association') == 'yes' ? true : false);
			}

			return true;
		}

	/*-------------------------------------------------------------------------
		Publish:
	-------------------------------------------------------------------------*/

		public function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){
			$entry_ids = array();

			if(!is_null($data['relation_id'])){
				if(!is_array($data['relation_id'])){
					$entry_ids = array($data['relation_id']);
				}
				else{
					$entry_ids = array_values($data['relation_id']);
				}

			}

			// build list of target entries
			$states = $this->findOptions($entry_ids);
			$options = array();

			if($this->get('required') != 'yes') {
				$options[] = array(NULL, false, NULL);
			}
			if($this->get('required') == 'yes') {
				$options[] = array('none', empty($entry_ids), __('Choose one'));
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

			// build label and input html
			$fieldname = 'fields' . $fieldnamePrefix . '[' . $this->get('element_name') . ']' . $fieldnamePostfix;
			if($this->get('allow_multiple_selection') == 'yes') $fieldname .= '[]';

			$html_attributes = array();
			if($this->get('allow_multiple_selection') == 'yes') {
				$html_attributes['multiple'] = 'multiple';
			}
			if($this->get('field_type') == 'autocomplete') {
				$html_attributes['class'] = 'reflink_replace';
			}
			$html_attributes['id'] = $fieldname;

			$label = Widget::Label($this->get('label'));
			if($this->get('required') != 'yes') {
				$label->appendChild(new XMLElement('i', __('Optional')));
			}
			$label->appendChild(Widget::Select($fieldname, $options, $html_attributes));

			if($flagWithError != NULL) {
				$wrapper->appendChild(Widget::Error($label, $flagWithError));
			}
			else {
				$wrapper->appendChild($label);
			}
		}

		public function processRawFieldData($data, &$status, &$message=null, $simulate = false, $entry_id = null) {
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
