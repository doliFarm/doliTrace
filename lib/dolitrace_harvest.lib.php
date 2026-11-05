<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 * Copyright (C) 2025       Frédéric France         <frederic.france@free.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/dolitrace_dolitraceharvest.lib.php
 * \ingroup dolitrace
 * \brief   Library files with common functions for DoliTraceHarvest
 */

/**
 * Prepare array of tabs for DoliTraceHarvest
 *
 * @param	DoliTraceHarvest	$object					DoliTraceHarvest
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function dolitraceharvestPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("dolitrace@dolitrace");

	$showtabofpagecontact = getDolGlobalInt('MAIN_DOLITRACE_SHOW_PAGE_OF_CONTACT');
	$showtabofpagenote = getDolGlobalInt('MAIN_DOLITRACE_SHOW_PAGE_OF_NOTE');
	$showtabofpagedocument = getDolGlobalInt('MAIN_DOLITRACE_SHOW_PAGE_OF_DOCUMENT');
	$showtabofpageagenda = getDolGlobalInt('MAIN_DOLITRACE_SHOW_PAGE_OF_AGENDA');

	$h = 0;
	$head = array();

	$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitraceharvest_card.php", 1), ['id' => $object->id]);
	$head[$h][1] = $langs->trans("DoliTraceHarvest");
	$head[$h][2] = 'card';
	$h++;

	if ($showtabofpagecontact) {
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitraceharvest_contact.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("Contacts");
		$head[$h][2] = 'contact';
		$h++;
	}

	if ($showtabofpagenote) {
		if (isset($object->fields['note_public']) || isset($object->fields['note_private'])) {
			$nbNote = 0;
			if (!empty($object->note_private)) {
				$nbNote++;
			}
			if (!empty($object->note_public)) {
				$nbNote++;
			}
			$head[$h][0] = dolBuildUrl(dol_buildpath('/dolitrace/dolitraceharvest_note.php', 1), ['id' => $object->id]);
			$head[$h][1] = $langs->trans('Notes');
			if ($nbNote > 0) {
				$head[$h][1] .= (!getDolGlobalInt('MAIN_OPTIMIZEFORTEXTBROWSER') ? '<span class="badge marginleftonlyshort">'.$nbNote.'</span>' : '');
			}
			$head[$h][2] = 'note';
			$h++;
		}
	}

	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';
		$upload_dir = $conf->dolitrace->dir_output."/dolitraceharvest/".dol_sanitizeFileName($object->ref);
		$nbFiles = count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$'));
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitraceharvest_document.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}

	if ($showtabofpageagenda) {
		$head[$h][0] = dolBuildUrl(dol_buildpath("/dolitrace/dolitraceharvest_agenda.php", 1), ['id' => $object->id]);
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}

	// Show more tabs from modules
	// Entries must be declared in modules descriptor with line
	//$this->tabs = array(
	//	'entity:+tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to add new tab
	//$this->tabs = array(
	//	'entity:-tabname:Title:@dolitrace:/dolitrace/mypage.php?id=__ID__'
	//); // to remove a tab
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitraceharvest@dolitrace');

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'dolitraceharvest@dolitrace', 'remove');

	return $head;
}

/**
 * Row of the create and edit forms of a harvest for its lot: a new lot (code proposed) or a lot of the other harvests
 * of the same product that is still open
 *
 * @param	Form				$form	Form
 * @param	DoliTraceHarvest	$object	Harvest
 * @return	string						HTML
 */
function dolitraceHarvestLotRow($form, $object)
{
	global $langs;

	$langs->loadLangs(array('dolitrace@dolitrace'));
	$lots = $object->getOpenLots();
	$posted = GETPOSTISSET('lot_mode');
	$current = (string) $object->batch_number;
	if ($posted) {
		$mode = (GETPOST('lot_mode', 'aZ09') == 'existing' ? 'existing' : 'new');
		$newcode = GETPOST('batch_number', 'alphanohtml');
		$existing = GETPOST('batch_existing', 'alphanohtml');
	} else {
		$mode = (($current !== '' && isset($lots[$current])) ? 'existing' : 'new');
		$newcode = (($current !== '' && !isset($lots[$current])) ? $current : $object->proposeLot());
		$existing = ($mode == 'existing' ? $current : '');
	}
	$names = array('BIO' => $langs->trans('SeedStatusBio'), 'INCONV' => $langs->trans('SeedStatusInConversion'), 'NONBIO' => $langs->trans('SeedStatusNonBio'));

	$out = '<tr><td class="titlefieldcreate">'.$form->textwithpicto($langs->trans('HarvestBatch'), $langs->trans('HarvestBatchHelp')).'</td><td>';
	$out .= '<div class="nowraponall"><label><input type="radio" name="lot_mode" id="lot_mode_new" value="new"'.($mode == 'new' ? ' checked' : '').'> '.$langs->trans('HarvestLotNew').'</label> ';
	$out .= '<input type="text" name="batch_number" id="batch_number" class="minwidth200" maxlength="120" value="'.dol_escape_htmltag($newcode).'"></div>';
	if (count($lots)) {
		$options = array();
		foreach ($lots as $lot) {
			$text = $lot['batch'].' - '.$langs->trans('HarvestLotHarvests', $lot['harvests']);
			if ($lot['qty'] > 0) {
				$text .= ', '.price($lot['qty'], 0, $langs, 0, -1, -1).($lot['unit'] !== '' ? ' '.$lot['unit'] : '');
			}
			if ($lot['date']) {
				$text .= ', '.$langs->trans('HarvestLotLast', dol_print_date($lot['date'], 'day'));
			}
			if ($lot['stock'] !== null) {
				$text .= ', '.$langs->trans('HarvestLotStock', price($lot['stock'], 0, $langs, 0, -1, -1));
			}
			if ($lot['bio_status'] !== '') {
				$text .= ' - '.($names[$lot['bio_status']] ?? $lot['bio_status']);
			}
			$options[$lot['batch']] = $text;
		}
		$out .= '<div class="margintoponlyshort nowraponall"><label><input type="radio" name="lot_mode" id="lot_mode_existing" value="existing"'.($mode == 'existing' ? ' checked' : '').'> '.$langs->trans('HarvestLotExisting').'</label> ';
		$out .= $form->selectarray('batch_existing', $options, $existing, 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500');
		$out .= '</div>';
		$out .= '<script>
		$(document).ready(function() {
			$("#batch_existing").on("change", function() { if ($(this).val() && $(this).val() != "-1") { $("#lot_mode_existing").prop("checked", true); } });
			$("#batch_number").on("focus input", function() { $("#lot_mode_new").prop("checked", true); });
		});
		</script>';
	} else {
		$out .= '<div class="opacitymedium small margintoponlyshort">'.$langs->trans($object->fk_product_out > 0 ? 'HarvestLotNoOpen' : 'HarvestLotNoProduct').'</div>';
	}
	if ($object->fk_product_out > 0 && !$object->isProductByLot()) {
		$out .= '<div class="warning margintoponlyshort">'.$langs->trans('HarvestLotNotByLot').'</div>';
	}
	$out .= '</td></tr>';
	return $out;
}
