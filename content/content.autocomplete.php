<?php

	Class contentExtensionReferencelinkAutocomplete extends AjaxPage{

		public function handleFailedAuthorisation(){
			$this->_status = self::STATUS_UNAUTHORISED;
			$this->_Result = json_encode(array('status' => __('You are not authorised to access this page.')));
		}

		public function view(){
			require_once(TOOLKIT . '/class.entrymanager.php');

			if(!isset($_GET['term'])) {
				$this->_Result = json_encode(array('status' => __('No results')));
				return;
			}
			else {
				$query = array(General::sanitize(urldecode($_GET['term'])));
				if(empty($query)) {
					$this->_Result = json_encode(array('status' => __('No results')));
					return;
				}
			}

			if(!isset($_GET['field-id'])) {
				$this->_Result = json_encode(array('status' => __('Missing <code>field-id</code>')));
				return;
			}
			else {
				$field_ids = explode(',',$_GET['field-id']);
				$results = array();

				foreach($field_ids as $field_id) {
					$field = FieldManager::fetch($field_id);
					$section_id = $field->get('parent_section');

					$joins = $where = '';
					$field->buildDSRetrievalSQL($query, $joins, $where, false);

					// Need to make it a wildcard search
					$where = str_replace("IN ('", "LIKE '%", $where);
					$where = str_replace("')", "%'", $where);

					// Fetch entries
					$entries = EntryManager::fetch(null, $section_id, 10, 0, $where, $joins, false, true, array($field->get('element_name')));

					foreach($entries as $entry) {
						$results[] = array(
							'id' => $entry->get('id'),
							'label' => $entry->getData($field->get('id'), true)->value
						);
					}
				}

				// Return
				$this->_Result = json_encode($results);
			}
		}

		public function generate(){
			header('Content-Type: application/json');
			echo $this->_Result;
			exit;
		}

	}