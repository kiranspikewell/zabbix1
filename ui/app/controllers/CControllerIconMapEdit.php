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


class CControllerIconMapEdit extends CController {

	protected function init() {
		$this->disableSIDValidation();
	}

	protected function checkInput() {
		$fields = [
			'iconmapid' => 'db icon_map.iconmapid',
			'iconmap'   => 'array'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions() {
		if (!$this->checkAccess(CRoleHelper::UI_ADMINISTRATION_GENERAL)) {
			return false;
		}

		if ($this->hasInput('iconmapid')) {
			$iconmaps = API::IconMap()->get([
				'output' => ['iconmapid', 'name', 'default_iconid'],
				'selectMappings' => ['inventory_link', 'expression', 'iconid', 'sortorder'],
				'iconmapids' => [$this->getInput('iconmapid')]
			]);

			if (!$iconmaps) {
				return false;
			}

			$this->iconmap = $this->getInput('iconmap', []) + reset($iconmaps);
		}
		else {
			$this->iconmap = $this->getInput('iconmap', []) + [
				'name' => '',
				'default_iconid' => 0,
				'mappings' => []
			];
		}

		return true;
	}

	protected function doAction() {
		order_result($this->iconmap['mappings'], 'sortorder');

		$inventory_list = getHostInventories();
		foreach ($inventory_list as &$field) {
			$field = $field['title'];
		}
		unset($field);

		$images = API::Image()->get([
			'output' => ['name'],
			'filter' => ['imagetype' => IMAGE_TYPE_ICON],
			'sortfield' => ['name'],
			'preservekeys' => true
		]);

		order_result($images, 'name');

		foreach ($images as &$icon) {
			$icon = $icon['name'];
		}
		unset($icon);

		$default_imageid = key($images);

		if (!$this->hasInput('iconmapid')) {
			$this->iconmap['default_iconid'] = $default_imageid;
		}

		$data = [
			'iconmapid' => $this->getInput('iconmapid', 0),
			'icon_list' => $images,
			'iconmap' => $this->iconmap,
			'inventory_list' => $inventory_list
		];

		$data['default_imageid'] = $default_imageid;

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of icon mapping'));

		$this->setResponse($response);
	}
}
