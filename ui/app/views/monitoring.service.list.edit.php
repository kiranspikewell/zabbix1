<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


/**
 * @var CView $this
 */

$this->addJsFile('layout.mode.js');

$this->includeJsFile('monitoring.service.list.js.php');

$this->enableLayoutModes();
$web_layout_mode = $this->getLayoutMode();

$filter = ($web_layout_mode == ZBX_LAYOUT_NORMAL)
	? (new CFilter($data['view_curl']))
		->setProfile('web.service.filter')
		->setActiveTab($data['active_tab'])
		->addFilterTab(_('Filter'), [
			(new CFormList())
				->addRow(_('Name'),
					(new CTextBox('filter_select', $data['filter']['name']))
						->setWidth(ZBX_TEXTAREA_FILTER_STANDARD_WIDTH)
				),
			(new CFormList())
				->addRow(_('Tags'), CTagFilterFieldHelper::getTagFilterField([
					'evaltype' => $data['filter']['evaltype'],
					'tags' => $data['filter']['tags']
				]))
		])
	: null;

$form = (new CForm())->setName('service_form');

$table = (new CTableInfo())
	->setHeader([
		(new CColHeader(
			(new CCheckBox('all_services'))->onClick("checkAll('".$form->getName()."', 'all_services', 'serviceids');")
		))->addClass(ZBX_STYLE_CELL_WIDTH),
		(new CColHeader(_('Name')))->addStyle('width: 40%'),
		(new CColHeader(_('Status')))->addStyle('width: 14%'),
		(new CColHeader(_('Root cause')))->addStyle('width: 24%'),
		(new CColHeader(_('SLA')))->addStyle('width: 14%'),
		(new CColHeader(_('Tags')))->addClass(ZBX_STYLE_COLUMN_TAGS_3),
		(new CColHeader())
	]);

foreach ($data['services'] as $serviceid => $service) {
	$dependencies_count = count($service['dependencies']);

	$table->addRow(new CRow([
		new CCheckBox('serviceid['.$serviceid.']', $serviceid),
		$dependencies_count > 0
			? [new CLink($service['name'], new CUrl('#')), CViewHelper::showNum($dependencies_count)]
			: $service['name'],
		'OK',
		'Root cause',
		sprintf('%.4f', $service['goodsla']),
		$data['tags'][$serviceid] ?? 'tags',
		(new CCol([
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_ADD)
				->setAttribute('data-id', $serviceid),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_EDIT)
				->setAttribute('data-id', $serviceid),
			(new CButton(null))
				->addClass(ZBX_STYLE_BTN_REMOVE)
				->setAttribute('data-id', $serviceid)
		]))->addClass(ZBX_STYLE_LIST_TABLE_ACTIONS)
	]));
}

$form->addItem($table);

(new CWidget())
	->setTitle(_('Services'))
	->setWebLayoutMode($web_layout_mode)
	->setControls(
		(new CTag('nav', true,
			(new CList())
				->addItem(
					(new CRedirectButton(_('Create service'),
						(new CUrl('zabbix.php'))
							->setArgument('action', 'dashboard.view')
							->setArgument('new', '1')
							->getUrl()
					))
				)
				->addItem(
					(new CRadioButtonList('list_mode', ZBX_LIST_MODE_EDIT))
						->addValue(_('View'), ZBX_LIST_MODE_VIEW)
						->addValue(_('Edit'), ZBX_LIST_MODE_EDIT)
						->setModern(true)
						->setId('list-mode')
				)
				->addItem(get_icon('kioskmode', ['mode' => $web_layout_mode]))
		))->setAttribute('aria-label', _('Content controls'))
	)
	->addItem($filter)
	->addItem($form)
	->show();

(new CScriptTag('
	initializeView();
'))
	->setOnDocumentReady()
	->show();

