<?php

// Data functions (insert, update, delete, form) for table units

// This script and data application were generated by AppGini 5.92
// Download AppGini for free from https://bigprof.com/appgini/download/

function units_insert(&$error_message = '') {
	global $Translation;

	// mm: can member insert record?
	$arrPerm = getTablePermissions('units');
	if(!$arrPerm['insert']) return false;

	$data = [
		'property' => Request::val('property', ''),
		'unit_number' => Request::val('unit_number', ''),
		'photo' => Request::fileUpload('photo', [
			'maxSize' => 2048000,
			'types' => 'jpg|jpeg|gif|png',
			'noRename' => false,
			'dir' => '',
			'success' => function($name, $selected_id) {
				createThumbnail($name, getThumbnailSpecs('units', 'photo', 'tv'));
				createThumbnail($name, getThumbnailSpecs('units', 'photo', 'dv'));
			},
			'failure' => function($selected_id, $fileRemoved) {
				if(!isset($_REQUEST['SelectedID'])) return '';

				/* for empty upload fields, when saving a copy of an existing record, copy the original upload field */
				return existing_value('units', 'photo', $_REQUEST['SelectedID']);
			},
		]),
		'status' => Request::val('status', ''),
		'size' => Request::val('size', ''),
		'country' => Request::lookup('property'),
		'street' => Request::lookup('property'),
		'city' => Request::lookup('property'),
		'state' => Request::lookup('property'),
		'postal_code' => Request::lookup('property'),
		'rooms' => Request::val('rooms', ''),
		'bathroom' => Request::val('bathroom', ''),
		'features' => Request::multipleChoice('features', ''),
		'market_rent' => Request::val('market_rent', ''),
		'rental_amount' => Request::val('rental_amount', ''),
		'deposit_amount' => Request::val('deposit_amount', ''),
		'description' => Request::val('description', ''),
	];

	if($data['status'] === '') {
		echo StyleSheet() . "\n\n<div class=\"alert alert-danger\">{$Translation['error:']} 'Status': {$Translation['field not null']}<br><br>";
		echo '<a href="" onclick="history.go(-1); return false;">' . $Translation['< back'] . '</a></div>';
		exit;
	}

	// hook: units_before_insert
	if(function_exists('units_before_insert')) {
		$args = [];
		if(!units_before_insert($data, getMemberInfo(), $args)) {
			if(isset($args['error_message'])) $error_message = $args['error_message'];
			return false;
		}
	}

	$error = '';
	// set empty fields to NULL
	$data = array_map(function($v) { return ($v === '' ? NULL : $v); }, $data);
	insert('units', backtick_keys_once($data), $error);
	if($error)
		die("{$error}<br><a href=\"#\" onclick=\"history.go(-1);\">{$Translation['< back']}</a>");

	$recID = db_insert_id(db_link());

	update_calc_fields('units', $recID, calculated_fields()['units']);

	// hook: units_after_insert
	if(function_exists('units_after_insert')) {
		$res = sql("SELECT * FROM `units` WHERE `id`='" . makeSafe($recID, false) . "' LIMIT 1", $eo);
		if($row = db_fetch_assoc($res)) {
			$data = array_map('makeSafe', $row);
		}
		$data['selectedID'] = makeSafe($recID, false);
		$args=[];
		if(!units_after_insert($data, getMemberInfo(), $args)) { return $recID; }
	}

	// mm: save ownership data
	set_record_owner('units', $recID, getLoggedMemberID());

	// if this record is a copy of another record, copy children if applicable
	if(!empty($_REQUEST['SelectedID'])) units_copy_children($recID, $_REQUEST['SelectedID']);

	return $recID;
}

function units_copy_children($destination_id, $source_id) {
	global $Translation;
	$requests = []; // array of curl handlers for launching insert requests
	$eo = ['silentErrors' => true];
	$uploads_dir = realpath(dirname(__FILE__) . '/../' . $Translation['ImageFolder']);
	$safe_sid = makeSafe($source_id);

	// launch requests, asynchronously
	curl_batch($requests);
}

function units_delete($selected_id, $AllowDeleteOfParents = false, $skipChecks = false) {
	// insure referential integrity ...
	global $Translation;
	$selected_id = makeSafe($selected_id);

	// mm: can member delete record?
	if(!check_record_permission('units', $selected_id, 'delete')) {
		return $Translation['You don\'t have enough permissions to delete this record'];
	}

	// hook: units_before_delete
	if(function_exists('units_before_delete')) {
		$args = [];
		if(!units_before_delete($selected_id, $skipChecks, getMemberInfo(), $args))
			return $Translation['Couldn\'t delete this record'] . (
				!empty($args['error_message']) ?
					'<div class="text-bold">' . strip_tags($args['error_message']) . '</div>'
					: '' 
			);
	}

	// child table: applications_leases
	$res = sql("SELECT `id` FROM `units` WHERE `id`='{$selected_id}'", $eo);
	$id = db_fetch_row($res);
	$rires = sql("SELECT COUNT(1) FROM `applications_leases` WHERE `unit`='" . makeSafe($id[0]) . "'", $eo);
	$rirow = db_fetch_row($rires);
	if($rirow[0] && !$AllowDeleteOfParents && !$skipChecks) {
		$RetMsg = $Translation["couldn't delete"];
		$RetMsg = str_replace('<RelatedRecords>', $rirow[0], $RetMsg);
		$RetMsg = str_replace('<TableName>', 'applications_leases', $RetMsg);
		return $RetMsg;
	} elseif($rirow[0] && $AllowDeleteOfParents && !$skipChecks) {
		$RetMsg = $Translation['confirm delete'];
		$RetMsg = str_replace('<RelatedRecords>', $rirow[0], $RetMsg);
		$RetMsg = str_replace('<TableName>', 'applications_leases', $RetMsg);
		$RetMsg = str_replace('<Delete>', '<input type="button" class="button" value="' . $Translation['yes'] . '" onClick="window.location = \'units_view.php?SelectedID=' . urlencode($selected_id) . '&delete_x=1&confirmed=1\';">', $RetMsg);
		$RetMsg = str_replace('<Cancel>', '<input type="button" class="button" value="' . $Translation[ 'no'] . '" onClick="window.location = \'units_view.php?SelectedID=' . urlencode($selected_id) . '\';">', $RetMsg);
		return $RetMsg;
	}

	// child table: unit_photos
	$res = sql("SELECT `id` FROM `units` WHERE `id`='{$selected_id}'", $eo);
	$id = db_fetch_row($res);
	$rires = sql("SELECT COUNT(1) FROM `unit_photos` WHERE `unit`='" . makeSafe($id[0]) . "'", $eo);
	$rirow = db_fetch_row($rires);
	if($rirow[0] && !$AllowDeleteOfParents && !$skipChecks) {
		$RetMsg = $Translation["couldn't delete"];
		$RetMsg = str_replace('<RelatedRecords>', $rirow[0], $RetMsg);
		$RetMsg = str_replace('<TableName>', 'unit_photos', $RetMsg);
		return $RetMsg;
	} elseif($rirow[0] && $AllowDeleteOfParents && !$skipChecks) {
		$RetMsg = $Translation['confirm delete'];
		$RetMsg = str_replace('<RelatedRecords>', $rirow[0], $RetMsg);
		$RetMsg = str_replace('<TableName>', 'unit_photos', $RetMsg);
		$RetMsg = str_replace('<Delete>', '<input type="button" class="button" value="' . $Translation['yes'] . '" onClick="window.location = \'units_view.php?SelectedID=' . urlencode($selected_id) . '&delete_x=1&confirmed=1\';">', $RetMsg);
		$RetMsg = str_replace('<Cancel>', '<input type="button" class="button" value="' . $Translation[ 'no'] . '" onClick="window.location = \'units_view.php?SelectedID=' . urlencode($selected_id) . '\';">', $RetMsg);
		return $RetMsg;
	}

	sql("DELETE FROM `units` WHERE `id`='{$selected_id}'", $eo);

	// hook: units_after_delete
	if(function_exists('units_after_delete')) {
		$args = [];
		units_after_delete($selected_id, getMemberInfo(), $args);
	}

	// mm: delete ownership data
	sql("DELETE FROM `membership_userrecords` WHERE `tableName`='units' AND `pkValue`='{$selected_id}'", $eo);
}

function units_update(&$selected_id, &$error_message = '') {
	global $Translation;

	// mm: can member edit record?
	if(!check_record_permission('units', $selected_id, 'edit')) return false;

	$data = [
		'property' => Request::val('property', ''),
		'unit_number' => Request::val('unit_number', ''),
		'photo' => Request::fileUpload('photo', [
			'maxSize' => 2048000,
			'types' => 'jpg|jpeg|gif|png',
			'noRename' => false,
			'dir' => '',
			'id' => $selected_id,
			'success' => function($name, $selected_id) {
				createThumbnail($name, getThumbnailSpecs('units', 'photo', 'tv'));
				createThumbnail($name, getThumbnailSpecs('units', 'photo', 'dv'));
			},
			'failure' => function($selected_id, $fileRemoved) {
				if($fileRemoved) return '';
				return existing_value('units', 'photo', $selected_id);
			},
		]),
		'status' => Request::val('status', ''),
		'size' => Request::val('size', ''),
		'country' => Request::lookup('property'),
		'street' => Request::lookup('property'),
		'city' => Request::lookup('property'),
		'state' => Request::lookup('property'),
		'postal_code' => Request::lookup('property'),
		'rooms' => Request::val('rooms', ''),
		'bathroom' => Request::val('bathroom', ''),
		'features' => Request::multipleChoice('features', ''),
		'market_rent' => Request::val('market_rent', ''),
		'rental_amount' => Request::val('rental_amount', ''),
		'deposit_amount' => Request::val('deposit_amount', ''),
		'description' => Request::val('description', ''),
	];

	if($data['status'] === '') {
		echo StyleSheet() . "\n\n<div class=\"alert alert-danger\">{$Translation['error:']} 'Status': {$Translation['field not null']}<br><br>";
		echo '<a href="" onclick="history.go(-1); return false;">' . $Translation['< back'] . '</a></div>';
		exit;
	}
	// get existing values
	$old_data = getRecord('units', $selected_id);
	if(is_array($old_data)) {
		$old_data = array_map('makeSafe', $old_data);
		$old_data['selectedID'] = makeSafe($selected_id);
	}

	$data['selectedID'] = makeSafe($selected_id);

	// hook: units_before_update
	if(function_exists('units_before_update')) {
		$args = ['old_data' => $old_data];
		if(!units_before_update($data, getMemberInfo(), $args)) {
			if(isset($args['error_message'])) $error_message = $args['error_message'];
			return false;
		}
	}

	$set = $data; unset($set['selectedID']);
	foreach ($set as $field => $value) {
		$set[$field] = ($value !== '' && $value !== NULL) ? $value : NULL;
	}

	if(!update(
		'units', 
		backtick_keys_once($set), 
		['`id`' => $selected_id], 
		$error_message
	)) {
		echo $error_message;
		echo '<a href="units_view.php?SelectedID=' . urlencode($selected_id) . "\">{$Translation['< back']}</a>";
		exit;
	}


	$eo = ['silentErrors' => true];

	update_calc_fields('units', $data['selectedID'], calculated_fields()['units']);

	// hook: units_after_update
	if(function_exists('units_after_update')) {
		$res = sql("SELECT * FROM `units` WHERE `id`='{$data['selectedID']}' LIMIT 1", $eo);
		if($row = db_fetch_assoc($res)) $data = array_map('makeSafe', $row);

		$data['selectedID'] = $data['id'];
		$args = ['old_data' => $old_data];
		if(!units_after_update($data, getMemberInfo(), $args)) return;
	}

	// mm: update ownership data
	sql("UPDATE `membership_userrecords` SET `dateUpdated`='" . time() . "' WHERE `tableName`='units' AND `pkValue`='" . makeSafe($selected_id) . "'", $eo);
}

function units_form($selected_id = '', $AllowUpdate = 1, $AllowInsert = 1, $AllowDelete = 1, $ShowCancel = 0, $TemplateDV = '', $TemplateDVP = '') {
	// function to return an editable form for a table records
	// and fill it with data of record whose ID is $selected_id. If $selected_id
	// is empty, an empty form is shown, with only an 'Add New'
	// button displayed.

	global $Translation;

	// mm: get table permissions
	$arrPerm = getTablePermissions('units');
	if(!$arrPerm['insert'] && $selected_id=='') { return ''; }
	$AllowInsert = ($arrPerm['insert'] ? true : false);
	// print preview?
	$dvprint = false;
	if($selected_id && $_REQUEST['dvprint_x'] != '') {
		$dvprint = true;
	}

	$filterer_property = thisOr($_REQUEST['filterer_property'], '');

	// populate filterers, starting from children to grand-parents

	// unique random identifier
	$rnd1 = ($dvprint ? rand(1000000, 9999999) : '');
	// combobox: property
	$combo_property = new DataCombo;
	// combobox: status
	$combo_status = new Combo;
	$combo_status->ListType = 2;
	$combo_status->MultipleSeparator = ', ';
	$combo_status->ListBoxHeight = 10;
	$combo_status->RadiosPerLine = 1;
	if(is_file(dirname(__FILE__).'/hooks/units.status.csv')) {
		$status_data = addslashes(implode('', @file(dirname(__FILE__).'/hooks/units.status.csv')));
		$combo_status->ListItem = explode('||', entitiesToUTF8(convertLegacyOptions($status_data)));
		$combo_status->ListData = $combo_status->ListItem;
	} else {
		$combo_status->ListItem = explode('||', entitiesToUTF8(convertLegacyOptions("Occupied;;Listed;;Unlisted")));
		$combo_status->ListData = $combo_status->ListItem;
	}
	$combo_status->SelectName = 'status';
	$combo_status->AllowNull = false;
	// combobox: features
	$combo_features = new Combo;
	$combo_features->ListType = 3;
	$combo_features->MultipleSeparator = ', ';
	$combo_features->ListBoxHeight = 10;
	$combo_features->RadiosPerLine = 1;
	if(is_file(dirname(__FILE__).'/hooks/units.features.csv')) {
		$features_data = addslashes(implode('', @file(dirname(__FILE__).'/hooks/units.features.csv')));
		$combo_features->ListItem = explode('||', entitiesToUTF8(convertLegacyOptions($features_data)));
		$combo_features->ListData = $combo_features->ListItem;
	} else {
		$combo_features->ListItem = explode('||', entitiesToUTF8(convertLegacyOptions("Cable ready;; Micorwave;;Hardwood floors;; High speed internet;;Air conditioning;;Refrigerator;;Dishwasher;;Walk-in closets;;Balcony;;Deck;;Patio;;Garage parking;;Carport;;Fenced yard;;Laundry room / hookups;; Fireplace;;Oven / range;;Heat - electric;; Heat - gas;; Heat - oil")));
		$combo_features->ListData = $combo_features->ListItem;
	}
	$combo_features->SelectName = 'features';

	if($selected_id) {
		// mm: check member permissions
		if(!$arrPerm['view']) return '';

		// mm: who is the owner?
		$ownerGroupID = sqlValue("SELECT `groupID` FROM `membership_userrecords` WHERE `tableName`='units' AND `pkValue`='" . makeSafe($selected_id) . "'");
		$ownerMemberID = sqlValue("SELECT LCASE(`memberID`) FROM `membership_userrecords` WHERE `tableName`='units' AND `pkValue`='" . makeSafe($selected_id) . "'");

		if($arrPerm['view'] == 1 && getLoggedMemberID() != $ownerMemberID) return '';
		if($arrPerm['view'] == 2 && getLoggedGroupID() != $ownerGroupID) return '';

		// can edit?
		$AllowUpdate = 0;
		if(($arrPerm['edit'] == 1 && $ownerMemberID == getLoggedMemberID()) || ($arrPerm['edit'] == 2 && $ownerGroupID == getLoggedGroupID()) || $arrPerm['edit'] == 3) {
			$AllowUpdate = 1;
		}

		$res = sql("SELECT * FROM `units` WHERE `id`='" . makeSafe($selected_id) . "'", $eo);
		if(!($row = db_fetch_array($res))) {
			return error_message($Translation['No records found'], 'units_view.php', false);
		}
		$combo_property->SelectedData = $row['property'];
		$combo_status->SelectedData = $row['status'];
		$combo_features->SelectedData = $row['features'];
		$urow = $row; /* unsanitized data */
		$hc = new CI_Input();
		$row = $hc->xss_clean($row); /* sanitize data */
	} else {
		$combo_property->SelectedData = $filterer_property;
		$combo_status->SelectedText = ( $_REQUEST['FilterField'][1] == '5' && $_REQUEST['FilterOperator'][1] == '<=>' ? $_REQUEST['FilterValue'][1] : '');
	}
	$combo_property->HTML = '<span id="property-container' . $rnd1 . '"></span><input type="hidden" name="property" id="property' . $rnd1 . '" value="' . html_attr($combo_property->SelectedData) . '">';
	$combo_property->MatchText = '<span id="property-container-readonly' . $rnd1 . '"></span><input type="hidden" name="property" id="property' . $rnd1 . '" value="' . html_attr($combo_property->SelectedData) . '">';
	$combo_status->Render();
	$combo_features->Render();

	ob_start();
	?>

	<script>
		// initial lookup values
		AppGini.current_property__RAND__ = { text: "", value: "<?php echo addslashes($selected_id ? $urow['property'] : $filterer_property); ?>"};

		jQuery(function() {
			setTimeout(function() {
				if(typeof(property_reload__RAND__) == 'function') property_reload__RAND__();
			}, 10); /* we need to slightly delay client-side execution of the above code to allow AppGini.ajaxCache to work */
		});
		function property_reload__RAND__() {
		<?php if(($AllowUpdate || $AllowInsert) && !$dvprint) { ?>

			$j("#property-container__RAND__").select2({
				/* initial default value */
				initSelection: function(e, c) {
					$j.ajax({
						url: 'ajax_combo.php',
						dataType: 'json',
						data: { id: AppGini.current_property__RAND__.value, t: 'units', f: 'property' },
						success: function(resp) {
							c({
								id: resp.results[0].id,
								text: resp.results[0].text
							});
							$j('[name="property"]').val(resp.results[0].id);
							$j('[id=property-container-readonly__RAND__]').html('<span id="property-match-text">' + resp.results[0].text + '</span>');
							if(resp.results[0].id == '<?php echo empty_lookup_value; ?>') { $j('.btn[id=properties_view_parent]').hide(); } else { $j('.btn[id=properties_view_parent]').show(); }


							if(typeof(property_update_autofills__RAND__) == 'function') property_update_autofills__RAND__();
						}
					});
				},
				width: '100%',
				formatNoMatches: function(term) { /* */ return '<?php echo addslashes($Translation['No matches found!']); ?>'; },
				minimumResultsForSearch: 5,
				loadMorePadding: 200,
				ajax: {
					url: 'ajax_combo.php',
					dataType: 'json',
					cache: true,
					data: function(term, page) { /* */ return { s: term, p: page, t: 'units', f: 'property' }; },
					results: function(resp, page) { /* */ return resp; }
				},
				escapeMarkup: function(str) { /* */ return str; }
			}).on('change', function(e) {
				AppGini.current_property__RAND__.value = e.added.id;
				AppGini.current_property__RAND__.text = e.added.text;
				$j('[name="property"]').val(e.added.id);
				if(e.added.id == '<?php echo empty_lookup_value; ?>') { $j('.btn[id=properties_view_parent]').hide(); } else { $j('.btn[id=properties_view_parent]').show(); }


				if(typeof(property_update_autofills__RAND__) == 'function') property_update_autofills__RAND__();
			});

			if(!$j("#property-container__RAND__").length) {
				$j.ajax({
					url: 'ajax_combo.php',
					dataType: 'json',
					data: { id: AppGini.current_property__RAND__.value, t: 'units', f: 'property' },
					success: function(resp) {
						$j('[name="property"]').val(resp.results[0].id);
						$j('[id=property-container-readonly__RAND__]').html('<span id="property-match-text">' + resp.results[0].text + '</span>');
						if(resp.results[0].id == '<?php echo empty_lookup_value; ?>') { $j('.btn[id=properties_view_parent]').hide(); } else { $j('.btn[id=properties_view_parent]').show(); }

						if(typeof(property_update_autofills__RAND__) == 'function') property_update_autofills__RAND__();
					}
				});
			}

		<?php } else { ?>

			$j.ajax({
				url: 'ajax_combo.php',
				dataType: 'json',
				data: { id: AppGini.current_property__RAND__.value, t: 'units', f: 'property' },
				success: function(resp) {
					$j('[id=property-container__RAND__], [id=property-container-readonly__RAND__]').html('<span id="property-match-text">' + resp.results[0].text + '</span>');
					if(resp.results[0].id == '<?php echo empty_lookup_value; ?>') { $j('.btn[id=properties_view_parent]').hide(); } else { $j('.btn[id=properties_view_parent]').show(); }

					if(typeof(property_update_autofills__RAND__) == 'function') property_update_autofills__RAND__();
				}
			});
		<?php } ?>

		}
	</script>
	<?php

	$lookups = str_replace('__RAND__', $rnd1, ob_get_contents());
	ob_end_clean();


	// code for template based detail view forms

	// open the detail view template
	if($dvprint) {
		$template_file = is_file("./{$TemplateDVP}") ? "./{$TemplateDVP}" : './templates/units_templateDVP.html';
		$templateCode = @file_get_contents($template_file);
	} else {
		$template_file = is_file("./{$TemplateDV}") ? "./{$TemplateDV}" : './templates/units_templateDV.html';
		$templateCode = @file_get_contents($template_file);
	}

	// process form title
	$templateCode = str_replace('<%%DETAIL_VIEW_TITLE%%>', 'Unit details', $templateCode);
	$templateCode = str_replace('<%%RND1%%>', $rnd1, $templateCode);
	$templateCode = str_replace('<%%EMBEDDED%%>', ($_REQUEST['Embedded'] ? 'Embedded=1' : ''), $templateCode);
	// process buttons
	if($AllowInsert) {
		if(!$selected_id) $templateCode = str_replace('<%%INSERT_BUTTON%%>', '<button type="submit" class="btn btn-success" id="insert" name="insert_x" value="1" onclick="return units_validateData();"><i class="glyphicon glyphicon-plus-sign"></i> ' . $Translation['Save New'] . '</button>', $templateCode);
		$templateCode = str_replace('<%%INSERT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="insert" name="insert_x" value="1" onclick="return units_validateData();"><i class="glyphicon glyphicon-plus-sign"></i> ' . $Translation['Save As Copy'] . '</button>', $templateCode);
	} else {
		$templateCode = str_replace('<%%INSERT_BUTTON%%>', '', $templateCode);
	}

	// 'Back' button action
	if($_REQUEST['Embedded']) {
		$backAction = 'AppGini.closeParentModal(); return false;';
	} else {
		$backAction = '$j(\'form\').eq(0).attr(\'novalidate\', \'novalidate\'); document.myform.reset(); return true;';
	}

	if($selected_id) {
		if(!$_REQUEST['Embedded']) $templateCode = str_replace('<%%DVPRINT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="dvprint" name="dvprint_x" value="1" onclick="$j(\'form\').eq(0).prop(\'novalidate\', true); document.myform.reset(); return true;" title="' . html_attr($Translation['Print Preview']) . '"><i class="glyphicon glyphicon-print"></i> ' . $Translation['Print Preview'] . '</button>', $templateCode);
		if($AllowUpdate) {
			$templateCode = str_replace('<%%UPDATE_BUTTON%%>', '<button type="submit" class="btn btn-success btn-lg" id="update" name="update_x" value="1" onclick="return units_validateData();" title="' . html_attr($Translation['Save Changes']) . '"><i class="glyphicon glyphicon-ok"></i> ' . $Translation['Save Changes'] . '</button>', $templateCode);
		} else {
			$templateCode = str_replace('<%%UPDATE_BUTTON%%>', '', $templateCode);
		}
		if(($arrPerm[4]==1 && $ownerMemberID==getLoggedMemberID()) || ($arrPerm[4]==2 && $ownerGroupID==getLoggedGroupID()) || $arrPerm[4]==3) { // allow delete?
			$templateCode = str_replace('<%%DELETE_BUTTON%%>', '<button type="submit" class="btn btn-danger" id="delete" name="delete_x" value="1" onclick="return confirm(\'' . $Translation['are you sure?'] . '\');" title="' . html_attr($Translation['Delete']) . '"><i class="glyphicon glyphicon-trash"></i> ' . $Translation['Delete'] . '</button>', $templateCode);
		} else {
			$templateCode = str_replace('<%%DELETE_BUTTON%%>', '', $templateCode);
		}
		$templateCode = str_replace('<%%DESELECT_BUTTON%%>', '<button type="submit" class="btn btn-default" id="deselect" name="deselect_x" value="1" onclick="' . $backAction . '" title="' . html_attr($Translation['Back']) . '"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['Back'] . '</button>', $templateCode);
	} else {
		$templateCode = str_replace('<%%UPDATE_BUTTON%%>', '', $templateCode);
		$templateCode = str_replace('<%%DELETE_BUTTON%%>', '', $templateCode);
		$templateCode = str_replace('<%%DESELECT_BUTTON%%>', ($ShowCancel ? '<button type="submit" class="btn btn-default" id="deselect" name="deselect_x" value="1" onclick="' . $backAction . '" title="' . html_attr($Translation['Back']) . '"><i class="glyphicon glyphicon-chevron-left"></i> ' . $Translation['Back'] . '</button>' : ''), $templateCode);
	}

	// set records to read only if user can't insert new records and can't edit current record
	if(($selected_id && !$AllowUpdate && !$AllowInsert) || (!$selected_id && !$AllowInsert)) {
		$jsReadOnly .= "\tjQuery('#property').prop('disabled', true).css({ color: '#555', backgroundColor: '#fff' });\n";
		$jsReadOnly .= "\tjQuery('#property_caption').prop('disabled', true).css({ color: '#555', backgroundColor: 'white' });\n";
		$jsReadOnly .= "\tjQuery('#unit_number').replaceWith('<div class=\"form-control-static\" id=\"unit_number\">' + (jQuery('#unit_number').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('#photo').replaceWith('<div class=\"form-control-static\" id=\"photo\">' + (jQuery('#photo').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('input[name=status]').parent().html('<div class=\"form-control-static\">' + jQuery('input[name=status]:checked').next().text() + '</div>')\n";
		$jsReadOnly .= "\tjQuery('#size').replaceWith('<div class=\"form-control-static\" id=\"size\">' + (jQuery('#size').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('#rooms').replaceWith('<div class=\"form-control-static\" id=\"rooms\">' + (jQuery('#rooms').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('#bathroom').replaceWith('<div class=\"form-control-static\" id=\"bathroom\">' + (jQuery('#bathroom').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('#features').replaceWith('<div class=\"form-control-static\" id=\"features\">' + (jQuery('#features').val() || '') + '</div>'); jQuery('#features-multi-selection-help').hide();\n";
		$jsReadOnly .= "\tjQuery('#s2id_features').remove();\n";
		$jsReadOnly .= "\tjQuery('#rental_amount').replaceWith('<div class=\"form-control-static\" id=\"rental_amount\">' + (jQuery('#rental_amount').val() || '') + '</div>');\n";
		$jsReadOnly .= "\tjQuery('.select2-container').hide();\n";

		$noUploads = true;
	} elseif($AllowInsert) {
		$jsEditable .= "\tjQuery('form').eq(0).data('already_changed', true);"; // temporarily disable form change handler
			$jsEditable .= "\tjQuery('form').eq(0).data('already_changed', false);"; // re-enable form change handler
	}

	// process combos
	$templateCode = str_replace('<%%COMBO(property)%%>', $combo_property->HTML, $templateCode);
	$templateCode = str_replace('<%%COMBOTEXT(property)%%>', $combo_property->MatchText, $templateCode);
	$templateCode = str_replace('<%%URLCOMBOTEXT(property)%%>', urlencode($combo_property->MatchText), $templateCode);
	$templateCode = str_replace('<%%COMBO(status)%%>', $combo_status->HTML, $templateCode);
	$templateCode = str_replace('<%%COMBOTEXT(status)%%>', $combo_status->SelectedData, $templateCode);
	$templateCode = str_replace('<%%COMBO(features)%%>', $combo_features->HTML, $templateCode);
	$templateCode = str_replace('<%%COMBOTEXT(features)%%>', $combo_features->SelectedData, $templateCode);

	/* lookup fields array: 'lookup field name' => array('parent table name', 'lookup field caption') */
	$lookup_fields = array('property' => array('properties', 'Property'), );
	foreach($lookup_fields as $luf => $ptfc) {
		$pt_perm = getTablePermissions($ptfc[0]);

		// process foreign key links
		if($pt_perm['view'] || $pt_perm['edit']) {
			$templateCode = str_replace("<%%PLINK({$luf})%%>", '<button type="button" class="btn btn-default view_parent hspacer-md" id="' . $ptfc[0] . '_view_parent" title="' . html_attr($Translation['View'] . ' ' . $ptfc[1]) . '"><i class="glyphicon glyphicon-eye-open"></i></button>', $templateCode);
		}

		// if user has insert permission to parent table of a lookup field, put an add new button
		if($pt_perm['insert'] && !$_REQUEST['Embedded']) {
			$templateCode = str_replace("<%%ADDNEW({$ptfc[0]})%%>", '<button type="button" class="btn btn-success add_new_parent hspacer-md" id="' . $ptfc[0] . '_add_new" title="' . html_attr($Translation['Add New'] . ' ' . $ptfc[1]) . '"><i class="glyphicon glyphicon-plus-sign"></i></button>', $templateCode);
		}
	}

	// process images
	$templateCode = str_replace('<%%UPLOADFILE(id)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(property)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(unit_number)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(photo)%%>', ($noUploads ? '' : "<div>{$Translation['upload image']}</div>" . '<i class="glyphicon glyphicon-remove text-danger clear-upload hidden"></i> <input type="file" name="photo" id="photo" data-filetypes="jpg|jpeg|gif|png" data-maxsize="2048000" accept=".jpg,.jpeg,.gif,.png">'), $templateCode);
	if($AllowUpdate && $row['photo'] != '') {
		$templateCode = str_replace('<%%REMOVEFILE(photo)%%>', '<br><input type="checkbox" name="photo_remove" id="photo_remove" value="1"> <label for="photo_remove" style="color: red; font-weight: bold;">'.$Translation['remove image'].'</label>', $templateCode);
	} else {
		$templateCode = str_replace('<%%REMOVEFILE(photo)%%>', '', $templateCode);
	}
	$templateCode = str_replace('<%%UPLOADFILE(status)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(size)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(rooms)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(bathroom)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(features)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(market_rent)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(rental_amount)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(deposit_amount)%%>', '', $templateCode);
	$templateCode = str_replace('<%%UPLOADFILE(description)%%>', '', $templateCode);

	// process values
	if($selected_id) {
		if( $dvprint) $templateCode = str_replace('<%%VALUE(id)%%>', safe_html($urow['id']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(id)%%>', html_attr($row['id']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(id)%%>', urlencode($urow['id']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(property)%%>', safe_html($urow['property']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(property)%%>', html_attr($row['property']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(property)%%>', urlencode($urow['property']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(unit_number)%%>', safe_html($urow['unit_number']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(unit_number)%%>', html_attr($row['unit_number']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(unit_number)%%>', urlencode($urow['unit_number']), $templateCode);
		$row['photo'] = ($row['photo'] != '' ? $row['photo'] : 'blank.gif');
		if( $dvprint) $templateCode = str_replace('<%%VALUE(photo)%%>', safe_html($urow['photo']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(photo)%%>', html_attr($row['photo']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(photo)%%>', urlencode($urow['photo']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(status)%%>', safe_html($urow['status']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(status)%%>', html_attr($row['status']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(status)%%>', urlencode($urow['status']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(size)%%>', safe_html($urow['size']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(size)%%>', html_attr($row['size']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(size)%%>', urlencode($urow['size']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(rooms)%%>', safe_html($urow['rooms']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(rooms)%%>', html_attr($row['rooms']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(rooms)%%>', urlencode($urow['rooms']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(bathroom)%%>', safe_html($urow['bathroom']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(bathroom)%%>', html_attr($row['bathroom']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(bathroom)%%>', urlencode($urow['bathroom']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(features)%%>', safe_html($urow['features']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(features)%%>', html_attr($row['features']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(features)%%>', urlencode($urow['features']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(market_rent)%%>', safe_html($urow['market_rent']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(market_rent)%%>', html_attr($row['market_rent']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(market_rent)%%>', urlencode($urow['market_rent']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(rental_amount)%%>', safe_html($urow['rental_amount']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(rental_amount)%%>', html_attr($row['rental_amount']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(rental_amount)%%>', urlencode($urow['rental_amount']), $templateCode);
		if( $dvprint) $templateCode = str_replace('<%%VALUE(deposit_amount)%%>', safe_html($urow['deposit_amount']), $templateCode);
		if(!$dvprint) $templateCode = str_replace('<%%VALUE(deposit_amount)%%>', html_attr($row['deposit_amount']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(deposit_amount)%%>', urlencode($urow['deposit_amount']), $templateCode);
		if($AllowUpdate || $AllowInsert) {
			$templateCode = str_replace('<%%HTMLAREA(description)%%>', '<textarea name="description" id="description" rows="5">' . html_attr($row['description']) . '</textarea>', $templateCode);
		} else {
			$templateCode = str_replace('<%%HTMLAREA(description)%%>', '<div id="description" class="form-control-static">' . $row['description'] . '</div>', $templateCode);
		}
		$templateCode = str_replace('<%%VALUE(description)%%>', nl2br($row['description']), $templateCode);
		$templateCode = str_replace('<%%URLVALUE(description)%%>', urlencode($urow['description']), $templateCode);
	} else {
		$templateCode = str_replace('<%%VALUE(id)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(id)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(property)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(property)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(unit_number)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(unit_number)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(photo)%%>', 'blank.gif', $templateCode);
		$templateCode = str_replace('<%%VALUE(status)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(status)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(size)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(size)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(rooms)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(rooms)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(bathroom)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(bathroom)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(features)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(features)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(market_rent)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(market_rent)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(rental_amount)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(rental_amount)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%VALUE(deposit_amount)%%>', '', $templateCode);
		$templateCode = str_replace('<%%URLVALUE(deposit_amount)%%>', urlencode(''), $templateCode);
		$templateCode = str_replace('<%%HTMLAREA(description)%%>', '<textarea name="description" id="description" rows="5"></textarea>', $templateCode);
	}

	// process translations
	foreach($Translation as $symbol=>$trans) {
		$templateCode = str_replace("<%%TRANSLATION($symbol)%%>", $trans, $templateCode);
	}

	// clear scrap
	$templateCode = str_replace('<%%', '<!-- ', $templateCode);
	$templateCode = str_replace('%%>', ' -->', $templateCode);

	// hide links to inaccessible tables
	if($_REQUEST['dvprint_x'] == '') {
		$templateCode .= "\n\n<script>\$j(function() {\n";
		$arrTables = getTableList();
		foreach($arrTables as $name => $caption) {
			$templateCode .= "\t\$j('#{$name}_link').removeClass('hidden');\n";
			$templateCode .= "\t\$j('#xs_{$name}_link').removeClass('hidden');\n";
		}

		$templateCode .= $jsReadOnly;
		$templateCode .= $jsEditable;

		if(!$selected_id) {
		}

		$templateCode.="\n});</script>\n";
	}

	// ajaxed auto-fill fields
	$templateCode .= '<script>';
	$templateCode .= '$j(function() {';

	$templateCode .= "\tproperty_update_autofills$rnd1 = function() {\n";
	$templateCode .= "\t\t\$j.ajax({\n";
	if($dvprint) {
		$templateCode .= "\t\t\turl: 'units_autofill.php?rnd1=$rnd1&mfk=property&id=' + encodeURIComponent('".addslashes($row['property'])."'),\n";
		$templateCode .= "\t\t\tcontentType: 'application/x-www-form-urlencoded; charset=" . datalist_db_encoding . "',\n";
		$templateCode .= "\t\t\ttype: 'GET'\n";
	} else {
		$templateCode .= "\t\t\turl: 'units_autofill.php?rnd1=$rnd1&mfk=property&id=' + encodeURIComponent(AppGini.current_property{$rnd1}.value),\n";
		$templateCode .= "\t\t\tcontentType: 'application/x-www-form-urlencoded; charset=" . datalist_db_encoding . "',\n";
		$templateCode .= "\t\t\ttype: 'GET',\n";
		$templateCode .= "\t\t\tbeforeSend: function() { \$j('#property$rnd1').prop('disabled', true); \$j('#propertyLoading').html('<img src=loading.gif align=top>'); },\n";
		$templateCode .= "\t\t\tcomplete: function() { " . (($arrPerm[1] || (($arrPerm[3] == 1 && $ownerMemberID == getLoggedMemberID()) || ($arrPerm[3] == 2 && $ownerGroupID == getLoggedGroupID()) || $arrPerm[3] == 3)) ? "\$j('#property$rnd1').prop('disabled', false); " : "\$j('#property$rnd1').prop('disabled', true); ")."\$j('#propertyLoading').html(''); \$j(window).resize(); }\n";
	}
	$templateCode .= "\t\t});\n";
	$templateCode .= "\t};\n";
	if(!$dvprint) $templateCode .= "\tif(\$j('#property_caption').length) \$j('#property_caption').click(function() { /* */ property_update_autofills$rnd1(); });\n";


	$templateCode.="});";
	$templateCode.="</script>";
	$templateCode .= $lookups;

	// handle enforced parent values for read-only lookup fields

	// don't include blank images in lightbox gallery
	$templateCode = preg_replace('/blank.gif" data-lightbox=".*?"/', 'blank.gif"', $templateCode);

	// don't display empty email links
	$templateCode=preg_replace('/<a .*?href="mailto:".*?<\/a>/', '', $templateCode);

	/* default field values */
	$rdata = $jdata = get_defaults('units');
	if($selected_id) {
		$jdata = get_joined_record('units', $selected_id);
		if($jdata === false) $jdata = get_defaults('units');
		$rdata = $row;
	}
	$templateCode .= loadView('units-ajax-cache', array('rdata' => $rdata, 'jdata' => $jdata));

	// hook: units_dv
	if(function_exists('units_dv')) {
		$args=[];
		units_dv(($selected_id ? $selected_id : FALSE), getMemberInfo(), $templateCode, $args);
	}

	return $templateCode;
}